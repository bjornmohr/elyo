<?php

namespace App\Http\Controllers\Company;

use App\Enums\Role;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateSurveyRequest;
use App\Http\Requests\Company\PatchSurveyRequest;
use App\Http\Resources\Company\SurveyResource;
use App\Http\Resources\Company\SurveyResultsResource;
use App\Models\Survey;
use App\Services\AnonymityService;
use App\Services\Company\TeamLayerGuard;
use App\Services\SurveyResultsAggregationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanySurveyController extends Controller
{
    public function __construct(
        private readonly SurveyResultsAggregationService $surveyResultsAggregationService,
        private readonly TeamLayerGuard $teamLayerGuard,
    )
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        // Route middleware already restricts to company roles
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);
        $this->teamLayerGuard->abortManagerWorkflowIfDisabled($user);

        $surveys = Survey::where('company_id', $user->company_id)
            ->when(! $teamLayerEnabled, fn (Builder $query) => $query->whereDoesntHave('teams'))
            ->when($this->isManagerOnly($user), function (Builder $query) use ($user) {
                $managedTeamIds = $this->managedTeamIds($user);

                $query->where(function (Builder $scope) use ($managedTeamIds, $user) {
                    $scope->whereDoesntHave('teams')
                        ->orWhereHas('teams', fn (Builder $teamQuery) => $teamQuery->whereIn('teams.id', $managedTeamIds))
                        ->orWhere('created_by', $user->id);
                });
            })
            ->withCount(['responses', 'questions'])
            ->with('teams:id')
            ->orderBy('created_at', 'desc')
            ->get();

        return SurveyResource::collection($surveys);
    }

    public function store(CreateSurveyRequest $request)
    {
        $this->abortDisabledTeamTargeting($request->user(), $request->input('teamIds', []));
        $this->teamLayerGuard->abortManagerWorkflowIfDisabled($request->user());

        return DB::transaction(function () use ($request) {
            $survey = Survey::create([
                'title' => $request->title,
                'description' => $request->description,
                'company_id' => $request->user()->company_id,
                'created_by' => $request->user()->id,
                'status' => 'DRAFT',
                'starts_at' => $request->startsAt,
                'ends_at' => $request->endsAt,
                'is_anonymous' => $request->boolean('isAnonymous', true),
            ]);

            foreach ($request->questions as $qData) {
                $survey->questions()->create([
                    'text' => $qData['text'],
                    'type' => $qData['type'],
                    'order' => $qData['order'],
                    'is_required' => $qData['isRequired'] ?? true,
                    'options' => isset($qData['options']) ? $qData['options'] : null,
                    'scale_min_label' => $qData['scaleMinLabel'] ?? null,
                    'scale_max_label' => $qData['scaleMaxLabel'] ?? null,
                ]);
            }

            $survey->teams()->sync($this->normalizedWritableTeamIds($request->user(), $request->teamIds ?? []));

            return new SurveyResource($survey->load(['teams:id'])->loadCount(['responses', 'questions']));
        });
    }

    public function show(Request $request, $id)
    {
        $survey = Survey::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->with(['teams:id', 'questions' => fn ($q) => $q->orderBy('order', 'asc')])
            ->withCount(['responses', 'questions'])
            ->firstOrFail();

        $this->abortDisabledTeamScopedSurvey($request->user(), $survey);
        abort_unless($this->canAccessSurvey($request->user(), $survey), 403);

        return new SurveyResource($survey);
    }

    public function update(PatchSurveyRequest $request, $id)
    {
        $survey = Survey::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->with(['teams:id', 'questions'])
            ->firstOrFail();

        $this->abortDisabledTeamScopedSurvey($request->user(), $survey);
        $this->abortDisabledTeamTargeting($request->user(), $request->input('teamIds', []), $request->has('teamIds'));
        abort_unless($this->canEditSurvey($request->user(), $survey), 403);

        $survey->update([
            'title' => $request->input('title', $survey->title),
            'description' => $request->input('description', $survey->description),
            'starts_at' => $request->input('startsAt', $survey->starts_at),
            'ends_at' => $request->input('endsAt', $survey->ends_at),
            'is_anonymous' => $request->boolean('isAnonymous', $survey->is_anonymous),
        ]);

        if ($request->has('teamIds')) {
            $survey->teams()->sync($this->normalizedWritableTeamIds($request->user(), $request->teamIds ?? []));
        }

        if ($request->has('questions')) {
            $survey->questions()->delete();
            foreach ($request->questions as $qData) {
                $survey->questions()->create([
                    'text' => $qData['text'],
                    'type' => $qData['type'],
                    'order' => $qData['order'],
                    'is_required' => $qData['isRequired'] ?? true,
                    'options' => $qData['options'] ?? null,
                    'scale_min_label' => $qData['scaleMinLabel'] ?? null,
                    'scale_max_label' => $qData['scaleMaxLabel'] ?? null,
                ]);
            }
        }

        return new SurveyResource($survey->refresh()->load(['teams:id', 'questions' => fn ($q) => $q->orderBy('order')])->loadCount(['responses', 'questions']));
    }

    public function activate(Request $request, $id)
    {
        $survey = Survey::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->with(['teams:id'])
            ->withCount('questions')
            ->firstOrFail();

        $this->abortDisabledTeamScopedSurvey($request->user(), $survey);
        abort_unless($this->canEditSurvey($request->user(), $survey), 403);

        if ($survey->questions_count < 1) {
            return response()->json(['message' => 'Eine Umfrage braucht mindestens eine Frage.'], 422);
        }

        $survey->update(['status' => SurveyStatus::ACTIVE]);

        return new SurveyResource($survey->refresh()->load(['teams:id'])->loadCount(['responses', 'questions']));
    }

    public function destroy(Request $request, $id)
    {
        $survey = Survey::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->with('teams:id')
            ->firstOrFail();

        $this->abortDisabledTeamScopedSurvey($request->user(), $survey);
        abort_unless($this->canEditSurvey($request->user(), $survey), 403);

        $survey->delete();

        return response()->json(['ok' => true]);
    }

    public function results(Request $request, $id)
    {
        $user = $request->user();
        // Route middleware already restricts to company roles

        $survey = Survey::where('id', $id)
            ->where('company_id', $user->company_id)
            ->with('teams:id')
            ->with(['questions' => fn ($q) => $q->orderBy('order', 'asc')])
            ->firstOrFail();

        $this->abortDisabledTeamScopedSurvey($user, $survey);
        abort_unless($this->canAccessSurvey($user, $survey), 403);

        if ($survey->status !== SurveyStatus::ACTIVE) {
            return response()->json(['message' => 'Ergebnisse sind erst fuer aktive Umfragen sichtbar.'], 409);
        }

        $company = $user->company;
        $threshold = $company->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;
        $scopeTeamIds = $this->resultScopeTeamIds($user, $survey);
        $results = $this->surveyResultsAggregationService->aggregate($survey, $scopeTeamIds, $threshold);

        if (! $results['isAboveThreshold']) {
            return response()->json([
                'error' => 'Zu wenige Antworten für anonyme Auswertung.',
                'minRequired' => $threshold,
                'isAboveThreshold' => false,
                'suppressionReason' => 'ANONYMITY_THRESHOLD_NOT_MET',
            ], 403);
        }

        $survey->is_above_threshold = true;
        $survey->questions_results = $results['questionResults'];
        $survey->scoped_response_count = $results['responseCount'];
        $survey->min_required = $threshold;
        $survey->participation = $results['participation'];
        $survey->result_scope = [
            'type' => $scopeTeamIds === null ? 'company' : 'teams',
            'teamIds' => $scopeTeamIds,
        ];

        return new SurveyResultsResource($survey);
    }

    private function isManagerOnly($user): bool
    {
        return $user->hasRole(Role::COMPANY_MANAGER) && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
    }

    private function managedTeamIds($user): array
    {
        return $user->managedTeams()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function canAccessSurvey($user, Survey $survey): bool
    {
        if (! $this->isManagerOnly($user)) {
            return true;
        }

        $managedTeamIds = $this->managedTeamIds($user);
        $surveyTeamIds = $survey->teams->pluck('id')->map(fn ($id) => (int) $id)->all();

        return empty($surveyTeamIds)
            || ! empty(array_intersect($managedTeamIds, $surveyTeamIds))
            || (int) $survey->created_by === (int) $user->id;
    }

    private function canEditSurvey($user, Survey $survey): bool
    {
        if ($survey->status !== SurveyStatus::DRAFT) {
            return false;
        }

        if (! $this->isManagerOnly($user)) {
            return true;
        }

        return (int) $survey->created_by === (int) $user->id;
    }

    private function normalizedWritableTeamIds($user, array $teamIds): array
    {
        $teamIds = array_values(array_unique(array_map('intval', $teamIds)));

        if (! $this->isManagerOnly($user)) {
            return $teamIds;
        }

        $managedTeamIds = $this->managedTeamIds($user);
        $teamIds = empty($teamIds) ? $managedTeamIds : $teamIds;

        abort_if(empty($teamIds) || ! empty(array_diff($teamIds, $managedTeamIds)), 403);

        return $teamIds;
    }

    private function resultScopeTeamIds($user, Survey $survey): ?array
    {
        $surveyTeamIds = $survey->teams->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! $this->isManagerOnly($user)) {
            return empty($surveyTeamIds) ? null : $surveyTeamIds;
        }

        $managedTeamIds = $this->managedTeamIds($user);
        $scopeTeamIds = empty($surveyTeamIds) ? $managedTeamIds : array_values(array_intersect($managedTeamIds, $surveyTeamIds));

        abort_if(empty($scopeTeamIds), 403);

        return $scopeTeamIds;
    }

    private function abortDisabledTeamTargeting($user, mixed $teamIds, bool $fieldPresent = true): void
    {
        if (! $fieldPresent || $this->teamLayerGuard->enabledFor($user)) {
            return;
        }

        if (! empty($teamIds)) {
            $this->teamLayerGuard->abortIfDisabled($user, 422);
        }
    }

    private function abortDisabledTeamScopedSurvey($user, Survey $survey): void
    {
        if ($this->teamLayerGuard->enabledFor($user)) {
            return;
        }

        if ($this->isManagerOnly($user) || $survey->teams->isNotEmpty()) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }
    }

}

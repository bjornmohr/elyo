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
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\AnonymityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanySurveyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // Route middleware already restricts to company roles

        $surveys = Survey::where('company_id', $user->company_id)
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

        abort_unless($this->canAccessSurvey($request->user(), $survey), 403);

        return new SurveyResource($survey);
    }

    public function update(PatchSurveyRequest $request, $id)
    {
        $survey = Survey::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->with(['teams:id', 'questions'])
            ->firstOrFail();

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

        abort_unless($this->canAccessSurvey($user, $survey), 403);

        if ($survey->status !== SurveyStatus::ACTIVE) {
            return response()->json(['message' => 'Ergebnisse sind erst fuer aktive Umfragen sichtbar.'], 409);
        }

        $company = $user->company;
        $threshold = $company->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;
        $scopeTeamIds = $this->resultScopeTeamIds($user, $survey);
        $responseCount = $this->scopedResponsesQuery($survey, $scopeTeamIds)->count();
        $eligibleCount = $this->eligibleUsersQuery($survey, $scopeTeamIds, $user->company_id)->count();

        if ($responseCount < $threshold) {
            return response()->json([
                'error' => 'Zu wenige Antworten für anonyme Auswertung.',
                'minRequired' => $threshold,
                'current' => $responseCount,
                'participation' => $this->participation($eligibleCount, $responseCount),
                'isAboveThreshold' => false,
            ], 403);
        }

        $questionResults = [];
        foreach ($survey->questions as $q) {
            $answerQuery = $this->scopedAnswersQuery($q->id, $survey, $scopeTeamIds);
            $answerCount = (clone $answerQuery)->count();
            $result = [
                'questionId' => $q->id,
                'text' => $q->text,
                'type' => $q->type->value,
                'answerCount' => $answerCount,
            ];

            if ($q->type->value === 'SCALE') {
                $agg = (clone $answerQuery)
                    ->whereNotNull('scale_value')
                    ->selectRaw('AVG(scale_value) as avg_value, MIN(scale_value) as min_value, MAX(scale_value) as max_value')
                    ->first();
                $result['avgValue'] = $agg->avg_value ? (float) round($agg->avg_value, 1) : null;
                $result['minValue'] = $agg->min_value ? (int) $agg->min_value : null;
                $result['maxValue'] = $agg->max_value ? (int) $agg->max_value : null;
                $result['scaleMinLabel'] = $q->scale_min_label;
                $result['scaleMaxLabel'] = $q->scale_max_label;
                $result['distribution'] = (clone $answerQuery)
                    ->whereNotNull('scale_value')
                    ->groupBy('scale_value')
                    ->selectRaw('scale_value as value, COUNT(*) as count')
                    ->orderBy('scale_value')
                    ->get()
                    ->map(fn ($item) => [
                        'value' => (int) $item->value,
                        'count' => (int) $item->count,
                        'percentage' => $answerCount > 0 ? round(((int) $item->count / $answerCount) * 100, 1) : 0,
                    ]);
            } elseif ($q->type->value === 'YES_NO') {
                $trueCount = (clone $answerQuery)->where('bool_value', true)->count();
                $result['trueCount'] = $trueCount;
                $result['falseCount'] = $answerCount - $trueCount;
                $result['truePercentage'] = $answerCount > 0 ? round(($trueCount / $answerCount) * 100, 1) : 0;
                $result['falsePercentage'] = $answerCount > 0 ? round((($answerCount - $trueCount) / $answerCount) * 100, 1) : 0;
            } elseif ($q->type->value === 'MULTIPLE_CHOICE') {
                $answers = (clone $answerQuery)
                    ->groupBy('choice_value')
                    ->selectRaw('choice_value as value, COUNT(*) as count')
                    ->orderByDesc('count')
                    ->get()
                    ->map(fn ($item) => [
                        'value' => $item->value,
                        'count' => (int) $item->count,
                        'percentage' => $answerCount > 0 ? round(((int) $item->count / $answerCount) * 100, 1) : 0,
                    ]);
                $result['options'] = $answers;
            }
            // TEXT only returns answerCount

            $questionResults[] = $result;
        }

        $survey->is_above_threshold = true;
        $survey->questions_results = $questionResults;
        $survey->scoped_response_count = $responseCount;
        $survey->min_required = $threshold;
        $survey->participation = $this->participation($eligibleCount, $responseCount);
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

    private function eligibleUsersQuery(Survey $survey, ?array $scopeTeamIds, int $companyId): Builder
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('roles', fn (Builder $query) => $query->where('role', Role::EMPLOYEE->value))
            ->when($scopeTeamIds !== null, fn (Builder $query) => $query->whereIn('team_id', $scopeTeamIds));
    }

    private function scopedResponsesQuery(Survey $survey, ?array $scopeTeamIds): Builder
    {
        return SurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->where('company_id', $survey->company_id)
            ->whereHas('user', function (Builder $query) use ($scopeTeamIds) {
                $query->where('status', 'active')
                    ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('role', Role::EMPLOYEE->value))
                    ->when($scopeTeamIds !== null, fn (Builder $teamQuery) => $teamQuery->whereIn('team_id', $scopeTeamIds));
            });
    }

    private function scopedAnswersQuery(int $questionId, Survey $survey, ?array $scopeTeamIds): Builder
    {
        return SurveyAnswer::query()
            ->where('question_id', $questionId)
            ->whereHas('response', fn (Builder $query) => $this->scopedResponseConstraints($query, $survey, $scopeTeamIds));
    }

    private function scopedResponseConstraints(Builder $query, Survey $survey, ?array $scopeTeamIds): void
    {
        $query->where('survey_id', $survey->id)
            ->where('company_id', $survey->company_id)
            ->whereHas('user', function (Builder $userQuery) use ($scopeTeamIds) {
                $userQuery->where('status', 'active')
                    ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('role', Role::EMPLOYEE->value))
                    ->when($scopeTeamIds !== null, fn (Builder $teamQuery) => $teamQuery->whereIn('team_id', $scopeTeamIds));
            });
    }

    private function participation(int $eligibleCount, int $responseCount): array
    {
        return [
            'eligibleCount' => $eligibleCount,
            'responseCount' => $responseCount,
            'rate' => $eligibleCount > 0 ? round(($responseCount / $eligibleCount) * 100, 1) : 0,
        ];
    }
}

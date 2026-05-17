<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateSurveyRequest;
use App\Http\Requests\Company\PatchSurveyRequest;
use App\Http\Resources\Company\SurveyResource;
use App\Http\Resources\Company\SurveyResultsResource;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyAnswer;
use App\Services\AnonymityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanySurveyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // Route middleware already restricts to company roles

        $surveys = Survey::where('company_id', $user->company_id)
            ->withCount(['responses', 'questions'])
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

            if ($request->filled('teamIds')) {
                $survey->teams()->sync($request->teamIds);
            }

            return new SurveyResource($survey->loadCount(['responses', 'questions']));
        });
    }

    public function update(PatchSurveyRequest $request, $id)
    {
        $survey = Survey::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $survey->update($request->validated());

        return new SurveyResource($survey->loadCount(['responses', 'questions']));
    }

    public function destroy(Request $request, $id)
    {
        $survey = Survey::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $survey->delete();

        return response()->json(['ok' => true]);
    }

    public function results(Request $request, $id)
    {
        $user = $request->user();
        // Route middleware already restricts to company roles

        $survey = Survey::where('id', $id)
            ->where('company_id', $user->company_id)
            ->with(['questions' => fn($q) => $q->orderBy('order', 'asc')])
            ->withCount('responses')
            ->firstOrFail();

        $company = $user->company;
        $threshold = $company->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;

        if ($survey->responses_count < $threshold) {
            return response()->json([
                'error' => 'Zu wenige Antworten für anonyme Auswertung.',
                'minRequired' => $threshold,
                'current' => $survey->responses_count,
                'isAboveThreshold' => false,
            ], 403);
        }

        $questionResults = [];
        foreach ($survey->questions as $q) {
            $result = [
                'questionId' => $q->id,
                'text' => $q->text,
                'type' => $q->type->value,
                'answerCount' => SurveyAnswer::where('question_id', $q->id)->count(),
            ];

            if ($q->type->value === 'SCALE') {
                $agg = SurveyAnswer::where('question_id', $q->id)
                    ->selectRaw('AVG(scale_value) as avg_value')
                    ->first();
                $result['avgValue'] = $agg->avg_value ? (float)round($agg->avg_value, 1) : null;
                $result['scaleMinLabel'] = $q->scale_min_label;
                $result['scaleMaxLabel'] = $q->scale_max_label;
            } elseif ($q->type->value === 'YES_NO') {
                $trueCount = SurveyAnswer::where('question_id', $q->id)->where('bool_value', true)->count();
                $result['trueCount'] = $trueCount;
                $result['falseCount'] = $result['answerCount'] - $trueCount;
            } elseif ($q->type->value === 'MULTIPLE_CHOICE') {
                $answers = SurveyAnswer::where('question_id', $q->id)
                    ->groupBy('choice_value')
                    ->selectRaw('choice_value as value, COUNT(*) as count')
                    ->orderByDesc('count')
                    ->get();
                $result['options'] = $answers;
            }
            // TEXT only returns answerCount

            $questionResults[] = $result;
        }

        $survey->is_above_threshold = true;
        $survey->questions_results = $questionResults;

        return new SurveyResultsResource($survey);
    }
}

<?php

namespace App\Http\Controllers\Employee;

use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\SurveyRespondRequest;
use App\Http\Resources\Employee\SurveyDetailResource;
use App\Http\Resources\Employee\SurveyResource;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $surveys = Survey::where('company_id', $user->company_id)
            ->where('status', SurveyStatus::ACTIVE)
            ->where(function ($query) use ($user) {
                $query->whereDoesntHave('teams');
                if ($user->team_id) {
                    $query->orWhereHas('teams', fn ($teamQuery) => $teamQuery->where('teams.id', $user->team_id));
                }
            })
            ->withCount('questions')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'surveys' => SurveyResource::collection($surveys),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $survey = Survey::where('id', $id)
            ->where('company_id', $user->company_id)
            ->where('status', SurveyStatus::ACTIVE)
            ->with(['questions' => fn ($q) => $q->orderBy('order', 'asc')])
            ->first();

        if (! $survey) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $existing = SurveyResponse::where('user_id', $user->id)
            ->where('survey_id', $id)
            ->exists();

        return response()->json([
            'survey' => new SurveyDetailResource($survey),
            'completed' => $existing,
        ]);
    }

    public function result(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $survey = Survey::where('id', $id)
            ->where('company_id', $user->company_id)
            ->with(['questions' => fn ($q) => $q->orderBy('order', 'asc')])
            ->first();

        if (! $survey) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $response = SurveyResponse::where('survey_id', $id)
            ->where('user_id', $user->id)
            ->with('answers')
            ->first();

        if (! $response) {
            return response()->json(['error' => 'No result for this survey'], 404);
        }

        $answersByQuestion = $response->answers->keyBy('question_id');

        return response()->json([
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'description' => $survey->description,
                'submittedAt' => $response->submitted_at?->toIso8601String(),
                'questions' => $survey->questions->map(function ($question) use ($answersByQuestion) {
                    $answer = $answersByQuestion->get($question->id);

                    return [
                        'id' => $question->id,
                        'text' => $question->text,
                        'type' => $question->type->value,
                        'answer' => [
                            'scaleValue' => $answer?->scale_value,
                            'textValue' => $answer?->text_value,
                            'choiceValue' => $answer?->choice_value,
                            'boolValue' => $answer?->bool_value,
                        ],
                    ];
                }),
            ],
        ]);
    }

    public function respond(SurveyRespondRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $survey = Survey::where('id', $id)
            ->where('company_id', $user->company_id)
            ->where('status', SurveyStatus::ACTIVE)
            ->with('questions')
            ->first();

        if (! $survey) {
            return response()->json(['error' => 'Survey not found or not active'], 404);
        }

        $existing = SurveyResponse::where('user_id', $user->id)
            ->where('survey_id', $id)
            ->exists();

        if ($existing) {
            return response()->json(['error' => 'Already completed'], 409);
        }

        $validQuestionIds = $survey->questions->pluck('id')->toArray();
        $answers = $request->validated()['answers'];

        foreach ($answers as $a) {
            if (! in_array($a['questionId'], $validQuestionIds)) {
                return response()->json(['error' => "Invalid questionId: {$a['questionId']}"], 400);
            }
        }

        $response = SurveyResponse::create([
            'survey_id' => $id,
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'submitted_at' => now(),
        ]);

        foreach ($answers as $a) {
            SurveyAnswer::create([
                'response_id' => $response->id,
                'question_id' => $a['questionId'],
                'scale_value' => $a['scaleValue'] ?? null,
                'text_value' => $a['textValue'] ?? null,
                'choice_value' => $a['choiceValue'] ?? null,
                'bool_value' => $a['boolValue'] ?? null,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}

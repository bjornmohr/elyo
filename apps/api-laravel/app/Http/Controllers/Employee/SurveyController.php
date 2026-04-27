<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\SurveyRespondRequest;
use App\Http\Resources\Employee\SurveyResource;
use App\Http\Resources\Employee\SurveyDetailResource;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyAnswer;
use App\Enums\SurveyStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SurveyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $surveys = Survey::where('company_id', $user->company_id)
            ->where('status', SurveyStatus::ACTIVE)
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
            ->with(['questions' => fn($q) => $q->orderBy('order', 'asc')])
            ->first();

        if (!$survey) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $existing = SurveyResponse::where('user_id', $user->id)
            ->where('survey_id', $id)
            ->exists();

        if ($existing) {
            return response()->json(['error' => 'Already completed'], 409);
        }

        return response()->json([
            'survey' => new SurveyDetailResource($survey),
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

        if (!$survey) {
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
            if (!in_array($a['questionId'], $validQuestionIds)) {
                return response()->json(['error' => "Invalid questionId: {$a['questionId']}"], 400);
            }
        }

        $response = SurveyResponse::create([
            'id' => (string) Str::orderedUuid(),
            'survey_id' => $id,
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'submitted_at' => now(),
        ]);

        foreach ($answers as $a) {
            SurveyAnswer::create([
                'id' => (string) Str::orderedUuid(),
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

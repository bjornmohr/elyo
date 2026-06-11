<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SurveyResultsAggregationService
{
    public function aggregate(Survey $survey, ?array $scopeTeamIds, int $threshold): array
    {
        $responseCount = $this->scopedResponsesQuery($survey, $scopeTeamIds)->count();
        $eligibleCount = $this->eligibleUsersQuery($scopeTeamIds, $survey->company_id)->count();
        $participation = $this->participation($eligibleCount, $responseCount);

        if ($responseCount < $threshold) {
            return [
                'isAboveThreshold' => false,
                'responseCount' => null,
                'participation' => null,
            ];
        }

        return [
            'isAboveThreshold' => true,
            'responseCount' => $responseCount,
            'participation' => $participation,
            'questionResults' => $this->questionResults($survey, $scopeTeamIds, $threshold),
        ];
    }

    private function questionResults(Survey $survey, ?array $scopeTeamIds, int $threshold): array
    {
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

            if ($answerCount > 0 && $answerCount < $threshold) {
                $questionResults[] = array_merge($result, [
                    'answerCount' => null,
                    'isSuppressed' => true,
                    'suppressedCount' => null,
                    'suppressionReason' => 'QUESTION_THRESHOLD_NOT_MET',
                ]);

                continue;
            }

            if ($q->type->value === 'SCALE') {
                $agg = (clone $answerQuery)
                    ->whereNotNull('scale_value')
                    ->selectRaw('AVG(scale_value) as avg_value, MIN(scale_value) as min_value, MAX(scale_value) as max_value')
                    ->first();
                $distribution = (clone $answerQuery)
                    ->whereNotNull('scale_value')
                    ->groupBy('scale_value')
                    ->selectRaw('scale_value as value, COUNT(*) as count')
                    ->orderBy('scale_value')
                    ->get();
                $suppressedCount = $distribution
                    ->filter(fn ($item) => $this->isSmallBucket((int) $item->count, $threshold))
                    ->sum(fn ($item) => (int) $item->count);
                $result['avgValue'] = $suppressedCount > 0 || $agg->avg_value === null ? null : (float) round($agg->avg_value, 1);
                $result['minValue'] = $suppressedCount > 0 || $agg->min_value === null ? null : (int) $agg->min_value;
                $result['maxValue'] = $suppressedCount > 0 || $agg->max_value === null ? null : (int) $agg->max_value;
                $result['scaleMinLabel'] = $q->scale_min_label;
                $result['scaleMaxLabel'] = $q->scale_max_label;
                $result['isSuppressed'] = $suppressedCount > 0;
                $result['suppressedCount'] = $suppressedCount > 0 ? null : 0;
                if ($suppressedCount > 0) {
                    $result['answerCount'] = null;
                    $result['suppressionReason'] = 'DISTRIBUTION_SUPPRESSED';
                }
                $result['distribution'] = $suppressedCount > 0
                    ? []
                    : $distribution
                        ->values()
                        ->map(fn ($item) => [
                            'value' => (int) $item->value,
                            'count' => (int) $item->count,
                            'percentage' => $answerCount > 0 ? round(((int) $item->count / $answerCount) * 100, 1) : 0,
                        ]);
            } elseif ($q->type->value === 'YES_NO') {
                $trueCount = (clone $answerQuery)->where('bool_value', true)->count();
                $falseCount = $answerCount - $trueCount;
                $isSuppressed = $this->isSmallBucket($trueCount, $threshold) || $this->isSmallBucket($falseCount, $threshold);
                $result['isSuppressed'] = $isSuppressed;
                $result['answerCount'] = $isSuppressed ? null : $answerCount;
                $result['suppressedCount'] = $isSuppressed ? null : 0;
                $result['trueCount'] = $isSuppressed ? null : $trueCount;
                $result['falseCount'] = $isSuppressed ? null : $falseCount;
                $result['truePercentage'] = ! $isSuppressed && $answerCount > 0 ? round(($trueCount / $answerCount) * 100, 1) : null;
                $result['falsePercentage'] = ! $isSuppressed && $answerCount > 0 ? round(($falseCount / $answerCount) * 100, 1) : null;
                if ($isSuppressed) {
                    $result['suppressionReason'] = 'DISTRIBUTION_SUPPRESSED';
                }
            } elseif ($q->type->value === 'MULTIPLE_CHOICE') {
                $choiceBuckets = (clone $answerQuery)
                    ->groupBy('choice_value')
                    ->selectRaw('choice_value as value, COUNT(*) as count')
                    ->orderByDesc('count')
                    ->get();
                $suppressedCount = $choiceBuckets
                    ->filter(fn ($item) => $this->isSmallBucket((int) $item->count, $threshold))
                    ->sum(fn ($item) => (int) $item->count);
                $isSuppressed = $suppressedCount > 0;
                $result['options'] = $isSuppressed
                    ? []
                    : $choiceBuckets
                        ->values()
                        ->map(fn ($item) => [
                            'value' => $item->value,
                            'count' => (int) $item->count,
                            'percentage' => $answerCount > 0 ? round(((int) $item->count / $answerCount) * 100, 1) : 0,
                        ]);
                $result['isSuppressed'] = $isSuppressed;
                $result['suppressedCount'] = $isSuppressed ? null : 0;
                if ($isSuppressed) {
                    $result['answerCount'] = null;
                    $result['suppressionReason'] = 'DISTRIBUTION_SUPPRESSED';
                }
            }
            // TEXT only returns answerCount

            $questionResults[] = $result;
        }

        return $questionResults;
    }

    private function eligibleUsersQuery(?array $scopeTeamIds, int $companyId): Builder
    {
        return User::query()
            ->where('company_id', $companyId)
            ->reportableForCompanyAggregates($scopeTeamIds);
    }

    private function scopedResponsesQuery(Survey $survey, ?array $scopeTeamIds): Builder
    {
        return SurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->where('company_id', $survey->company_id)
            ->whereHas('user', fn (Builder $query) => $query->reportableForCompanyAggregates($scopeTeamIds));
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
            ->whereHas('user', fn (Builder $userQuery) => $userQuery->reportableForCompanyAggregates($scopeTeamIds));
    }

    private function participation(int $eligibleCount, int $responseCount): array
    {
        return [
            'eligibleCount' => $eligibleCount,
            'responseCount' => $responseCount,
            'rate' => $eligibleCount > 0 ? round(($responseCount / $eligibleCount) * 100, 1) : 0,
        ];
    }

    private function isSmallBucket(int $count, int $threshold): bool
    {
        return $count > 0 && $count < $threshold;
    }
}

<?php

namespace App\Services;

use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MeasureParticipationService
{
    public function __construct(
        protected PointsService $pointsService
    ) {}

    public function participate(User $user, int|string $measureId): MeasureParticipation
    {
        $measure = $this->findEmployeeVisibleMeasure($user, $measureId);

        if ($measure->status !== 'ACTIVE') {
            throw new ConflictHttpException('MEASURE_NOT_ACTIVE');
        }

        if ($this->hasParticipation($user, $measure)) {
            throw new ConflictHttpException('MEASURE_ALREADY_PARTICIPATED');
        }

        try {
            return DB::transaction(function () use ($user, $measure) {
                $participation = MeasureParticipation::create([
                    'measure_id' => $measure->id,
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                    'team_id' => $user->team_id,
                    'participated_at' => now(),
                ]);

                $this->pointsService->awardPoints($user, 'measure_participation');

                return $participation;
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateParticipation($exception)) {
                throw new ConflictHttpException('MEASURE_ALREADY_PARTICIPATED', $exception);
            }

            throw $exception;
        }
    }

    public function findEmployeeVisibleMeasure(User $user, int|string $measureId): Measure
    {
        $measure = Measure::query()
            ->whereKey($measureId)
            ->where('company_id', $user->company_id)
            ->where(function ($query) use ($user) {
                $query->whereNull('team_id');

                if ($user->team_id) {
                    $query->orWhere('team_id', $user->team_id);
                }
            })
            ->first();

        if (! $measure) {
            throw new NotFoundHttpException;
        }

        return $measure;
    }

    private function hasParticipation(User $user, Measure $measure): bool
    {
        return MeasureParticipation::query()
            ->where('measure_id', $measure->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function isDuplicateParticipation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $message = $exception->getMessage();

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains($message, 'measure_participations_measure_id_user_id_unique');
    }
}

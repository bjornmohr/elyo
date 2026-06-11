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

        if ($measure->verification_requirement !== Measure::VERIFICATION_REQUIREMENT_SELF_REPORT) {
            throw new ConflictHttpException('MEASURE_REQUIRES_QR_CHECKIN');
        }

        return $this->createParticipation($user, $measure, MeasureParticipation::VERIFICATION_TYPE_SELF_REPORTED);
    }

    public function participateByQrCheckin(User $user, Measure $measure, ?callable $onSuccess = null): MeasureParticipation
    {
        if ((int) $measure->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException;
        }

        if ($measure->team_id !== null && (int) $measure->team_id !== (int) $user->team_id) {
            throw new NotFoundHttpException;
        }

        if ($measure->verification_requirement !== Measure::VERIFICATION_REQUIREMENT_QR_CODE) {
            throw new ConflictHttpException('MEASURE_DOES_NOT_ALLOW_QR_CHECKIN');
        }

        return $this->createParticipation($user, $measure, MeasureParticipation::VERIFICATION_TYPE_QR_CHECKIN, $onSuccess);
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

    private function createParticipation(User $user, Measure $measure, string $verificationType, ?callable $onSuccess = null): MeasureParticipation
    {
        if ($measure->status !== 'ACTIVE') {
            throw new ConflictHttpException('MEASURE_NOT_ACTIVE');
        }

        if ($this->hasParticipation($user, $measure)) {
            throw new ConflictHttpException('MEASURE_ALREADY_PARTICIPATED');
        }

        try {
            return DB::transaction(function () use ($user, $measure, $verificationType, $onSuccess) {
                $participatedAt = now();

                $participation = MeasureParticipation::create([
                    'measure_id' => $measure->id,
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                    'team_id' => $user->team_id,
                    'participated_at' => $participatedAt,
                    'verification_type' => $verificationType,
                    'verified_at' => $participatedAt,
                    'verified_by_user_id' => null,
                ]);

                $this->pointsService->awardPoints($user, 'measure_participation');

                if ($onSuccess) {
                    $onSuccess($participation);
                }

                return $participation;
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateParticipation($exception)) {
                throw new ConflictHttpException('MEASURE_ALREADY_PARTICIPATED', $exception);
            }

            throw $exception;
        }
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

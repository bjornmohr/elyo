<?php

namespace App\Services;

use App\Models\Measure;
use App\Models\MeasureCheckinToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MeasureCheckinTokenService
{
    public function rotate(Measure $measure, User $creator): array
    {
        if ($measure->status !== 'ACTIVE') {
            throw new ConflictHttpException('MEASURE_NOT_ACTIVE');
        }

        if ($measure->verification_requirement !== Measure::VERIFICATION_REQUIREMENT_QR_CODE) {
            throw new ConflictHttpException('MEASURE_DOES_NOT_ALLOW_QR_CHECKIN');
        }

        return DB::transaction(function () use ($measure, $creator): array {
            MeasureCheckinToken::query()
                ->where('measure_id', $measure->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $rawToken = bin2hex(random_bytes(32));
            $checkinToken = MeasureCheckinToken::create([
                'measure_id' => $measure->id,
                'company_id' => $measure->company_id,
                'token_hash' => self::hashToken($rawToken),
                'created_by_user_id' => $creator->id,
                'valid_from' => now(),
            ]);

            return [$checkinToken, $rawToken];
        });
    }

    public function findTokenByRawToken(string $rawToken): ?MeasureCheckinToken
    {
        return MeasureCheckinToken::query()
            ->with('measure')
            ->where('token_hash', self::hashToken($rawToken))
            ->first();
    }

    public function validateTokenLifecycle(MeasureCheckinToken $checkinToken): void
    {
        if ($checkinToken->revoked_at !== null) {
            throw new ConflictHttpException('CHECKIN_TOKEN_REVOKED');
        }

        if ($checkinToken->valid_from !== null && $checkinToken->valid_from->isFuture()) {
            throw new ConflictHttpException('CHECKIN_TOKEN_NOT_YET_VALID');
        }

        if ($checkinToken->valid_until !== null && $checkinToken->valid_until->isPast()) {
            throw new ConflictHttpException('CHECKIN_TOKEN_EXPIRED');
        }
    }

    public function markUsed(MeasureCheckinToken $checkinToken): void
    {
        $checkinToken->forceFill(['last_used_at' => now()])->save();
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}

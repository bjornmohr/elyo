<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeasureParticipationFactory extends Factory
{
    protected $model = MeasureParticipation::class;

    public function definition(): array
    {
        $context = null;
        $resolveContext = function (array $attributes) use (&$context): array {
            if ($context !== null) {
                return $context;
            }

            $measureId = is_numeric($attributes['measure_id'] ?? null) ? (int) $attributes['measure_id'] : null;
            $userId = is_numeric($attributes['user_id'] ?? null) ? (int) $attributes['user_id'] : null;
            $companyId = is_numeric($attributes['company_id'] ?? null) ? (int) $attributes['company_id'] : null;

            $measure = $measureId ? Measure::query()->find($measureId) : null;
            $user = $userId ? User::query()->find($userId) : null;
            $teamId = is_numeric($attributes['team_id'] ?? null)
                ? (int) $attributes['team_id']
                : ($measure?->team_id ?? $user?->team_id);

            $companyId ??= $measure?->company_id
                ?? $user?->company_id
                ?? Company::factory()->create()->id;

            $userId ??= User::factory()->create([
                'company_id' => $companyId,
                'team_id' => $teamId,
            ])->id;

            $measureId ??= Measure::factory()->create([
                'company_id' => $companyId,
                'team_id' => $teamId,
                'created_by' => $userId,
            ])->id;

            return $context = [
                'company_id' => $companyId,
                'team_id' => $teamId,
                'user_id' => $userId,
                'measure_id' => $measureId,
            ];
        };

        return [
            'company_id' => fn (array $attributes): int => $resolveContext($attributes)['company_id'],
            'team_id' => fn (array $attributes): ?int => $resolveContext($attributes)['team_id'],
            'user_id' => fn (array $attributes): int => $resolveContext($attributes)['user_id'],
            'measure_id' => fn (array $attributes): int => $resolveContext($attributes)['measure_id'],
            'participated_at' => now(),
            'verification_type' => MeasureParticipation::VERIFICATION_TYPE_SELF_REPORTED,
            'verified_at' => fn (array $attributes) => $attributes['participated_at'] ?? now(),
            'verified_by_user_id' => null,
        ];
    }

    public function forTeamMeasure(): static
    {
        return $this->state(function () {
            $company = Company::factory()->create();
            $manager = User::factory()->create([
                'company_id' => $company->id,
                'team_id' => null,
            ]);
            $team = Team::factory()->create([
                'company_id' => $company->id,
                'manager_id' => $manager->id,
            ]);
            $user = User::factory()->create([
                'company_id' => $company->id,
                'team_id' => $team->id,
            ]);
            $measure = Measure::factory()->create([
                'company_id' => $company->id,
                'team_id' => $team->id,
                'created_by' => $manager->id,
            ]);

            return [
                'measure_id' => $measure->id,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'team_id' => $team->id,
            ];
        });
    }
}

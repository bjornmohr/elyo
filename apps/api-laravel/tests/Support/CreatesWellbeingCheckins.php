<?php

namespace Tests\Support;

use App\Models\Health\WellbeingEntry;
use App\Models\User;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;

/**
 * Creates wellbeing check-ins the way production does: the identity is resolved
 * to a health subject through the mapping domain, and the entry is written in
 * the health domain on that subject only.
 */
trait CreatesWellbeingCheckins
{
    protected function healthSubjectIdFor(User $user): string
    {
        return app(MappingServiceContract::class)->provisionOwnSubject(
            $user->id,
            PurposeCode::PROVISIONING,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createWellbeingEntry(User $user, array $attributes = []): WellbeingEntry
    {
        return WellbeingEntry::factory()->create([
            'health_subject_id' => $this->healthSubjectIdFor($user),
            ...$attributes,
        ]);
    }
}

<?php

namespace Tests\Feature\Health;

use App\Models\Health\LabMarker;
use App\Models\Health\LabMarkerReading;
use App\Models\User;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\LabMarkerCatalogSeeder;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

class LabMarkerSeederTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('lab-marker-seeder-test');
    }

    public function test_catalog_contains_the_complete_demo_reference_marker_set(): void
    {
        $this->seed(LabMarkerCatalogSeeder::class);

        $this->assertSame(
            ['crp', 'ery', 'ferritin', 'hb', 'hkt', 'leuko', 'mch', 'mchc', 'mcv', 'rdw', 'thrombo', 'vitd'],
            LabMarker::query()->orderBy('marker_key')->pluck('marker_key')->all(),
        );
        $this->assertSame(12, LabMarker::query()->where('active', true)->count());
        $this->assertSame(
            ['blutbild', 'immun', 'mikro'],
            LabMarker::query()->distinct()->orderBy('marker_group')->pluck('marker_group')->all(),
        );
    }

    public function test_re_seeding_the_catalog_keeps_the_original_creation_timestamp(): void
    {
        $this->seed(LabMarkerCatalogSeeder::class);
        $createdAt = LabMarker::query()->findOrFail('crp')->created_at;

        $this->travel(1)->hours();
        $this->seed(LabMarkerCatalogSeeder::class);

        $this->assertTrue(
            $createdAt->equalTo(LabMarker::query()->findOrFail('crp')->created_at),
            'Re-seeding must update the catalog, not recreate it.',
        );
        $this->assertSame(12, LabMarker::query()->count());
    }

    public function test_demo_employee_gets_synthetic_history_through_the_health_subject(): void
    {
        $this->seed(DemoDataSeeder::class);

        $employee = User::query()->where('email', 'employee1@demo.de')->sole();
        $subjectId = app(MappingServiceContract::class)->resolveOwnSubject(
            $employee->id,
            PurposeCode::HEALTH_SELF_READ,
        );
        $readings = LabMarkerReading::query()
            ->where('health_subject_id', $subjectId)
            ->orderBy('measured_at')
            ->get();

        $this->assertGreaterThanOrEqual(3, $readings->count());
        $this->assertGreaterThan(1, $readings->where('marker_key', 'ferritin')->count());
        $this->assertSame(['manual'], $readings->pluck('source')->unique()->values()->all());
    }
}

<?php

namespace Tests\Feature;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_mockup_measures_for_demo_company(): void
    {
        $this->seed(DemoDataSeeder::class);

        $companyId = DB::table('companies')->where('slug', 'demo-gmbh')->value('id');
        $measures = DB::table('measures')->where('company_id', $companyId)->get()->keyBy('title');

        $workshop = $measures['Rückenfit Bürostuhl-Workshop'];
        $this->assertSame('flexibility', $workshop->category);
        $this->assertSame('ACTIVE', $workshop->status);
        $this->assertSame('QR_CODE', $workshop->verification_requirement);
        $this->assertSame(80, (int) $workshop->capacity);
        $this->assertSame('Kantine EG', $workshop->location_name);
        $this->assertSame(1, DB::table('measure_checkin_tokens')->where('measure_id', $workshop->id)->count());

        $this->assertSame('COMPLETED', $measures['Vitamin-Info-Stand']->status);
        $this->assertSame('SUGGESTED', $measures['Ernährungs-Webinar']->status);
        $this->assertSame('sport', $measures['Lauftreff Mittwochs']->category);
        $this->assertSame('sport', $measures['Yoga am Morgen']->category);
        $this->assertSame('mental', $measures['Achtsamkeits-Session']->category);

        // Participation quotas above the anonymity threshold of 3.
        $this->assertSame(5, DB::table('measure_participations')->where('measure_id', $workshop->id)->count());
        $this->assertSame(4, DB::table('measure_participations')->where('measure_id', $measures['Vitamin-Info-Stand']->id)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $companyId = DB::table('companies')->where('slug', 'demo-gmbh')->value('id');

        $this->assertSame(1, DB::table('measures')
            ->where('company_id', $companyId)
            ->where('title', 'Rückenfit Bürostuhl-Workshop')
            ->count());
        $this->assertSame(1, DB::table('companies')->where('slug', 'demo-gmbh')->count());
    }
}

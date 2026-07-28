<?php

namespace Tests\Privacy;

use Illuminate\Support\Facades\DB;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\Support\PrivacySeeder;
use Tests\TestCase;

class AuditInvariantPrivacyTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    protected $connectionsToTransact = ['identity', 'mapping', 'health'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('privacy-audit-invariant');
        DB::connection('audit_migrator')->table('audit_events')->delete();
    }

    protected function tearDown(): void
    {
        DB::connection('audit_migrator')->table('audit_events')->delete();

        parent::tearDown();
    }

    public function test_sampled_audit_rows_never_contain_user_and_subject_references_together(): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();

        $rows = DB::connection('audit_migrator')
            ->table('audit_events')
            ->select(['user_ref', 'subject_ref'])
            ->limit(100)
            ->get();

        $this->assertTrue($rows->isNotEmpty(), 'Privacy seed produced no audit rows to sample.');

        foreach ($rows as $index => $row) {
            $this->assertFalse(
                $row->user_ref !== null && $row->subject_ref !== null,
                "Audit row at sample index {$index} contains both reference types.",
            );
        }
    }
}

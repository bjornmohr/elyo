<?php

namespace Tests\Feature;

use Tests\TestCase;

class MeasureParticipationTestEnvironmentIsolationTest extends TestCase
{
    public function test_measure_participation_filtered_tests_use_isolated_sqlite_memory_database(): void
    {
        $this->assertSame([
            'app_env' => 'testing',
            'db_connection' => 'sqlite',
            'db_database' => ':memory:',
        ], [
            'app_env' => app()->environment(),
            'db_connection' => config('database.default'),
            'db_database' => config('database.connections.'.config('database.default').'.database'),
        ]);
    }
}

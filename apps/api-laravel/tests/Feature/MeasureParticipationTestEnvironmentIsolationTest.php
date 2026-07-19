<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeasureParticipationTestEnvironmentIsolationTest extends TestCase
{
    public function test_filtered_tests_use_isolated_postgres_databases_with_expected_roles(): void
    {
        $connections = [
            'identity' => ['elyo_identity_test', 'elyo_identity_rt'],
            'mapping' => ['elyo_subject_mapping_test', 'elyo_mapping_svc'],
            'health' => ['elyo_health_test', 'elyo_employee_rt'],
            'audit' => ['elyo_audit_test', 'elyo_employee_rt'],
            'identity_migrator' => ['elyo_identity_test', 'elyo_migrator'],
            'mapping_migrator' => ['elyo_subject_mapping_test', 'elyo_migrator'],
            'health_migrator' => ['elyo_health_test', 'elyo_migrator'],
            'audit_migrator' => ['elyo_audit_test', 'elyo_migrator'],
        ];

        $this->assertSame('testing', app()->environment());
        $this->assertSame('identity', config('database.default'));

        foreach ($connections as $connectionName => [$expectedDatabase, $expectedRole]) {
            $this->assertSame('pgsql', config("database.connections.{$connectionName}.driver"));
            $this->assertSame($expectedDatabase, config("database.connections.{$connectionName}.database"));
            $this->assertSame($expectedRole, config("database.connections.{$connectionName}.username"));

            $actual = DB::connection($connectionName)
                ->selectOne('SELECT current_database() AS database_name, current_user AS role_name');

            $this->assertSame($expectedDatabase, $actual->database_name);
            $this->assertSame($expectedRole, $actual->role_name);
        }
    }
}

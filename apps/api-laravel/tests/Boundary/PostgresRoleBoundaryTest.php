<?php

namespace Tests\Boundary;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class PostgresRoleBoundaryTest extends BoundaryTestCase
{
    public function test_employee_runtime_can_touch_only_sanctum_usage_timestamps(): void
    {
        $identityMigrator = DB::connection('identity_migrator');
        $tokenId = $identityMigrator->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => 1,
            'name' => 'boundary-session-continuity',
            'token' => hash('sha256', 'boundary-session-continuity'),
            'abilities' => '["*"]',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        try {
            $connection = $this->boundaryConnection(
                'employee_token_touch',
                'identity',
                'elyo_employee_rt',
                (string) env('DB_HEALTH_PASSWORD'),
            );
            $this->assertCurrentUser($connection, 'elyo_employee_rt');

            $touchedAt = now()->startOfSecond();
            $this->assertSame(1, $connection->table('personal_access_tokens')
                ->where('id', $tokenId)
                ->update([
                    'last_used_at' => $touchedAt,
                    'updated_at' => $touchedAt,
                ]));

            $token = $identityMigrator->table('personal_access_tokens')->find($tokenId);
            $this->assertSame($touchedAt->toDateTimeString(), $token->last_used_at);
            $this->assertSame($touchedAt->toDateTimeString(), $token->updated_at);
        } finally {
            $identityMigrator->table('personal_access_tokens')->where('id', $tokenId)->delete();
        }
    }

    public function test_employee_runtime_cannot_write_protected_identity_data(): void
    {
        $identityMigrator = DB::connection('identity_migrator');
        $tokenId = $identityMigrator->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => 1,
            'name' => 'boundary-protected-token-data',
            'token' => hash('sha256', 'boundary-protected-token-data'),
            'abilities' => '["*"]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $connection = $this->boundaryConnection(
                'employee_protected_identity',
                'identity',
                'elyo_employee_rt',
                (string) env('DB_HEALTH_PASSWORD'),
            );
            $this->assertCurrentUser($connection, 'elyo_employee_rt');
            $this->assertFalse((bool) $identityMigrator->scalar(
                "SELECT has_table_privilege('elyo_employee_rt', 'public.personal_access_tokens', 'UPDATE')",
            ));

            foreach ([
                'token' => hash('sha256', 'changed-boundary-token'),
                'abilities' => '["read"]',
                'expires_at' => now()->addHour(),
                'tokenable_id' => 2,
                'tokenable_type' => 'App\\Models\\Partner',
            ] as $column => $value) {
                $this->assertDatabaseOperationDenied(
                    fn () => $connection->table('personal_access_tokens')
                        ->where('id', $tokenId)
                        ->update([$column => $value]),
                    'permission denied',
                    "Employee runtime unexpectedly updated personal_access_tokens.{$column}.",
                );
            }

            $this->assertDatabaseOperationDenied(
                fn () => $connection->table('users')
                    ->whereRaw('1 = 0')
                    ->update(['name' => 'changed']),
                'permission denied',
                'Employee runtime unexpectedly updated an ordinary identity table.',
            );
        } finally {
            $identityMigrator->table('personal_access_tokens')->where('id', $tokenId)->delete();
        }
    }

    public function test_identity_runtime_cannot_read_subject_mappings(): void
    {
        $connection = $this->boundaryConnection(
            'identity_to_mapping',
            'mapping',
            'elyo_identity_rt',
            (string) env('DB_IDENTITY_PASSWORD'),
        );

        $this->assertDatabaseOperationDenied(
            fn () => $connection->table('subject_mappings')->count(),
            'permission denied',
            'Identity runtime unexpectedly read subject mappings.',
        );
    }

    public function test_company_runtime_cannot_connect_to_health_database(): void
    {
        $connection = $this->boundaryConnection(
            'company_to_health',
            'health',
            'elyo_company_rt',
            (string) env('ELYO_COMPANY_RT_PASSWORD'),
        );

        $this->assertDatabaseOperationDenied(
            fn () => $connection->scalar('SELECT current_user'),
            'permission denied',
            'Company runtime unexpectedly connected to the health database.',
        );
    }

    public function test_mapping_runtime_can_read_mapping_database(): void
    {
        $connection = $this->boundaryConnection(
            'mapping_positive',
            'mapping',
            'elyo_mapping_svc',
            (string) env('DB_MAPPING_PASSWORD'),
        );

        $this->assertCurrentUser($connection, 'elyo_mapping_svc');
        $this->assertSame(0, $connection->table('subject_mappings')->count());
    }

    public function test_employee_runtime_can_read_health_database(): void
    {
        $connection = $this->boundaryConnection(
            'health_positive',
            'health',
            'elyo_employee_rt',
            (string) env('DB_HEALTH_PASSWORD'),
        );

        $this->assertCurrentUser($connection, 'elyo_employee_rt');
        $this->assertSame(0, $connection->table('health_subjects')->count());
    }

    public function test_mapping_runtime_cannot_read_identity_tables(): void
    {
        $connection = $this->boundaryConnection(
            'mapping_to_identity',
            'identity',
            'elyo_mapping_svc',
            (string) env('DB_MAPPING_PASSWORD'),
        );

        $this->assertDatabaseOperationDenied(
            fn () => $connection->table('users')->count(),
            'permission denied',
            'Mapping runtime unexpectedly read identity tables.',
        );

        $identityMigrator = DB::connection('identity_migrator');
        $identityTables = $identityMigrator->table('pg_catalog.pg_tables')
            ->where('schemaname', 'public')
            ->pluck('tablename');

        $this->assertContains('users', $identityTables);

        foreach ($identityTables as $identityTable) {
            $this->assertFalse(
                (bool) $identityMigrator->scalar(
                    "SELECT has_table_privilege('elyo_mapping_svc', 'public.' || quote_ident(?), 'SELECT')",
                    [(string) $identityTable],
                ),
                "Mapping runtime unexpectedly has SELECT on identity table {$identityTable}.",
            );
        }
    }

    #[DataProvider('auditRuntimeRoles')]
    public function test_audit_is_append_only_for_runtime_roles(
        string $role,
        string $passwordEnvironmentVariable,
    ): void {
        $auditProbeTable = sprintf(
            'boundary_audit_probe_%d_%d',
            getmypid() ?: 0,
            spl_object_id($this),
        );
        $probeCreated = false;

        try {
            DB::connection('audit_migrator')->statement(
                "CREATE TABLE {$auditProbeTable} (id bigserial PRIMARY KEY, note text NOT NULL)",
            );
            $probeCreated = true;

            $connection = $this->boundaryConnection(
                'audit_'.str_replace('elyo_', '', $role),
                'audit',
                $role,
                (string) env($passwordEnvironmentVariable),
            );

            $this->assertCurrentUser($connection, $role);
            $connection->table($auditProbeTable)->insert(['note' => 'synthetic']);

            $this->assertDatabaseOperationDenied(
                fn () => $connection->table($auditProbeTable)->update(['note' => 'changed']),
                'permission denied for table',
                "{$role} unexpectedly updated audit data.",
            );
            $this->assertDatabaseOperationDenied(
                fn () => $connection->table($auditProbeTable)->delete(),
                'permission denied for table',
                "{$role} unexpectedly deleted audit data.",
            );
        } finally {
            if ($probeCreated) {
                DB::connection('audit_migrator')->statement("DROP TABLE IF EXISTS {$auditProbeTable}");
            }

            DB::purge('audit_migrator');
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function auditRuntimeRoles(): array
    {
        return [
            'employee runtime' => ['elyo_employee_rt', 'DB_HEALTH_PASSWORD'],
            'company runtime' => ['elyo_company_rt', 'ELYO_COMPANY_RT_PASSWORD'],
            'mapping runtime' => ['elyo_mapping_svc', 'DB_MAPPING_PASSWORD'],
        ];
    }
}

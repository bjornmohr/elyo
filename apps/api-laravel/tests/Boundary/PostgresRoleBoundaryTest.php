<?php

namespace Tests\Boundary;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class PostgresRoleBoundaryTest extends BoundaryTestCase
{
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

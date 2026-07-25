<?php

namespace Tests\Boundary;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class HealthSchemaBoundaryTest extends BoundaryTestCase
{
    /**
     * ADR-003 D3 / AGENTS.md health-domain rules: health tables carry no identity
     * or employer column. This walks the whole health schema rather than a known
     * table list, so a future health table cannot reintroduce one unnoticed.
     */
    public function test_no_health_table_carries_an_identity_or_employer_column(): void
    {
        $schemaBuilder = Schema::connection('health');
        $tables = collect($schemaBuilder->getTables())->pluck('name');

        $this->assertNotEmpty($tables, 'The health schema is empty — migrations did not run.');
        $this->assertContains('wellbeing_entries', $tables->all());

        $violations = [];

        foreach ($tables as $table) {
            foreach (['user_id', 'company_id', 'team_id', 'email'] as $forbiddenColumn) {
                if ($schemaBuilder->hasColumn($table, $forbiddenColumn)) {
                    $violations[] = "{$table}.{$forbiddenColumn}";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Health tables must be keyed on health_subject_id only: '.implode(', ', $violations),
        );
    }

    public function test_wellbeing_entries_are_keyed_on_a_health_subject(): void
    {
        $schemaBuilder = Schema::connection('health');

        $this->assertTrue($schemaBuilder->hasColumn('wellbeing_entries', 'health_subject_id'));
        $this->assertFalse(
            $schemaBuilder->hasColumn('wellbeing_entries', 'note'),
            'The free-text note was removed from the check-in (ELYO-102 §3.3).',
        );
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    public static function offScaleValues(): array
    {
        return [
            'mood below scale' => ['mood', 0],
            'mood above scale' => ['mood', 6],
            'stress above scale' => ['stress', 6],
            'energy above scale' => ['energy', 6],
        ];
    }

    /**
     * The 1–5 scale is pinned in the database, not only in the Form Request, so
     * no writer can persist an off-scale value.
     */
    #[DataProvider('offScaleValues')]
    public function test_database_rejects_values_outside_the_canonical_scale(
        string $column,
        int $offScaleValue,
    ): void {
        $subjectId = (string) Str::ulid();

        DB::connection('health')->table('health_subjects')->insert([
            'id' => $subjectId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = [
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'mood' => 3,
            'stress' => 3,
            'energy' => 3,
            'score' => 3.0,
            'period_key' => '2026-05-25',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $row[$column] = $offScaleValue;

        // Nothing may query the health connection after this point: PostgreSQL
        // aborts the surrounding test transaction on a constraint violation.
        $this->assertDatabaseOperationDenied(
            fn () => DB::connection('health')->table('wellbeing_entries')->insert($row),
            'violates check constraint',
            "The database accepted {$column} = {$offScaleValue}.",
        );
    }
}

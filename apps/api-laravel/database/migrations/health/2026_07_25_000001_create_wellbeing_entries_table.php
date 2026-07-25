<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wellbeing check-ins in the health domain (ELYO-110/109, ADR-003 D3).
 *
 * The identity-side `wellbeing_entries` table is dropped in the identity
 * domain; this is its replacement. Deliberately absent: `user_id`, `company_id`
 * and the free-text `note` (ELYO-102 §3.3 / DSFA R5). Entries are keyed on
 * `health_subject_id` only, so nothing in this database can be attributed to an
 * identity or an employer without the mapping domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wellbeing_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('health_subject_id')
                ->constrained('health_subjects')
                ->cascadeOnDelete();
            $table->smallInteger('mood');
            $table->smallInteger('stress');
            $table->smallInteger('energy');
            $table->double('score');
            $table->string('period_key');
            $table->timestamps();

            $table->unique(['health_subject_id', 'period_key']);
        });

        // Canonical 1–5 scale (ELYO-102 §3.1) enforced in the database, not only
        // in the Form Request, so no writer can persist an off-scale value.
        DB::statement(<<<'SQL'
            ALTER TABLE wellbeing_entries
                ADD CONSTRAINT wellbeing_entries_mood_scale_check CHECK (mood BETWEEN 1 AND 5),
                ADD CONSTRAINT wellbeing_entries_stress_scale_check CHECK (stress BETWEEN 1 AND 5),
                ADD CONSTRAINT wellbeing_entries_energy_scale_check CHECK (energy BETWEEN 1 AND 5)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('wellbeing_entries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anamnesis profiles in the health domain (ELYO-91 prompt 08a, ADR-003 D8).
 *
 * Replaces the identity-side `anamnesis_profiles` table, which is dropped in the
 * identity domain. Deliberately absent: `user_id` and `company_id` — the profile
 * is keyed on `health_subject_id` only (ADR-001 §2.6), so nothing in this
 * database attributes an anamnesis to an identity or an employer without the
 * mapping domain.
 *
 * Deletion behaviour: the only cascade is from the subject. There is no cascade
 * from `users` any more, because the identity database cannot reach this table.
 * Subject-scoped deletion is wired by the retention flow (prompt 13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamnesis_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('health_subject_id')
                ->unique()
                ->constrained('health_subjects')
                ->cascadeOnDelete();
            $table->integer('completion_pct')->default(0);
            $table->integer('birth_year')->nullable();
            $table->string('biological_sex')->nullable();
            $table->string('activity_level')->nullable();
            $table->string('sleep_quality')->nullable();
            $table->string('stress_tendency')->nullable();
            $table->string('smoking_status')->nullable();
            $table->string('nutrition_type')->nullable();
            $table->jsonb('chronic_patterns')->nullable();
            $table->boolean('has_medication')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anamnesis_profiles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the identity-side `wellbeing_entries` table (ELYO-110, ADR-003 D3).
 *
 * Check-ins now live in the health domain on `health_subject_id`. This is a
 * follow-up migration rather than an edit of the reviewed prompt-03 baseline:
 * the execution plan (`docs/ai-tasks/2026-07-19-00-elyo-91-execution-plan.md`,
 * "Failure / drift rules") requires schema changes after prompt 03 to be new
 * migration files so review traceability is preserved. No data is migrated —
 * the app is pre-production (ELYO-135 mapping is therefore not needed here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wellbeing_entries');
    }

    /**
     * Recreates the dropped baseline table verbatim so the migration is
     * reversible. It is intentionally never written to again.
     */
    public function down(): void
    {
        Schema::create('wellbeing_entries', function (Blueprint $table): void {
            $table->id();
            $table->integer('mood');
            $table->integer('stress');
            $table->integer('energy');
            $table->double('score');
            $table->text('note')->nullable();
            $table->string('period_key');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['user_id', 'period_key']);
            $table->index(['company_id', 'period_key']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }
};

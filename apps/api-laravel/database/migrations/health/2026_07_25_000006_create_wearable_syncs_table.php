<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wearable measurement snapshots in the health domain (ELYO-91 prompt 08a,
 * ADR-003 D8).
 *
 * Replaces the identity-side `wearable_syncs` table. Steps, heart rate, sleep
 * and recovery are health data of the highest sensitivity, so they are keyed on
 * `health_subject_id` only; the old `unique(user_id, source, date)` becomes
 * `unique(health_subject_id, source, date)`.
 *
 * The feature is dormant (no route, no caller — see the prompt-08a report).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wearable_syncs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('health_subject_id')
                ->constrained('health_subjects')
                ->cascadeOnDelete();
            $table->string('source');
            $table->timestamp('date');
            $table->integer('steps')->nullable();
            $table->double('heart_rate')->nullable();
            $table->double('sleep_hours')->nullable();
            $table->double('recovery_score')->nullable();
            $table->double('hrv')->nullable();
            $table->double('readiness')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();

            $table->unique(['health_subject_id', 'source', 'date']);
            $table->index(['health_subject_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wearable_syncs');
    }
};

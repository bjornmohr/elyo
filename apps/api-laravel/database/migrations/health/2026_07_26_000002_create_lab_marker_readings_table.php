<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-oriented lab readings in the health domain (ELYO-105).
 *
 * Deliberately absent: `user_id` and stored status. Identity resolution stays
 * outside this domain; soft status is derived from catalog bounds. No unique
 * subject/marker constraint exists because readings form a dated history.
 *
 * The subject FK cascades like every other health table: deleting a health
 * subject removes its readings. There is deliberately no cascade from identity
 * users — subject-level deletion is governed by the retention flow (prompt 13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_marker_readings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('health_subject_id')
                ->constrained('health_subjects')
                ->cascadeOnDelete();
            $table->string('marker_key');
            $table->foreign('marker_key')
                ->references('marker_key')
                ->on('lab_markers');
            $table->decimal('value', 12, 4);
            $table->date('measured_at');
            $table->enum('source', ['manual', 'document_import', 'bgm_import']);
            $table->timestamps();

            $table->index(
                ['health_subject_id', 'marker_key', 'measured_at'],
                'lab_readings_subject_marker_measured_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_marker_readings');
    }
};

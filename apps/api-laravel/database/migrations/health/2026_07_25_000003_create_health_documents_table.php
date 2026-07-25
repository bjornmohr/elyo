<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health-document catalogue metadata in the health domain (ELYO-91 prompt 08a,
 * ADR-003 D8).
 *
 * Replaces the identity-side `health_documents` table. The table is dormant —
 * no route, service or seeder writes it today (see the prompt-08a report) — but
 * it is health data by definition, so it moves with the rest of the domain
 * instead of leaving a `user_id`-keyed health table behind in identity.
 *
 * File bytes are untouched here; storage hardening (own bucket, signed URLs,
 * virus scan) is the ADR-001 §2.9 storage hardening follow-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('health_subject_id')
                ->constrained('health_subjects')
                ->cascadeOnDelete();
            $table->string('type');
            $table->string('file_name');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_documents');
    }
};

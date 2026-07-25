<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the identity-side health tables (ELYO-91 prompt 08a, ADR-003 D8).
 *
 * Anamnesis profiles, health-document metadata, uploaded medical-document
 * metadata and wearable connection/sync data now live in the health domain on
 * `health_subject_id`. Same mechanism as prompt 08 chose for
 * `wellbeing_entries`: a follow-up migration rather than an edit of the reviewed
 * prompt-03 baseline, because the execution plan
 * (`docs/ai-tasks/2026-07-19-00-elyo-91-execution-plan.md`, "Failure / drift
 * rules") requires schema changes after prompt 03 to be new migration files so
 * review traceability is preserved. No data is migrated — the app is
 * pre-production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wearable_syncs');
        Schema::dropIfExists('wearable_connections');
        Schema::dropIfExists('user_documents');
        Schema::dropIfExists('health_documents');
        Schema::dropIfExists('anamnesis_profiles');
    }

    /**
     * Recreates the dropped baseline tables verbatim so the migration is
     * reversible. They are intentionally never written to again.
     */
    public function down(): void
    {
        Schema::create('anamnesis_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
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

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('health_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->string('file_name');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('file_name');
            $table->string('blob_url');
            $table->string('blob_key');
            $table->string('mime_type');
            $table->integer('size');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('wearable_connections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('source');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'source']);
        });

        Schema::create('wearable_syncs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
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

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'source', 'date']);
            $table->index(['user_id', 'date']);
        });
    }
};

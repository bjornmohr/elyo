<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wearable provider connections in the health domain (ELYO-91 prompt 08a,
 * ADR-003 D8).
 *
 * Replaces the identity-side `wearable_connections` table. A connection binds a
 * provider account to a health subject, never to an identity: the old
 * `unique(user_id, source)` becomes `unique(health_subject_id, source)`.
 *
 * The feature is dormant (no route, no caller — see the prompt-08a report); the
 * table moves for schema consistency, without any feature build-out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wearable_connections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('health_subject_id')
                ->constrained('health_subjects')
                ->cascadeOnDelete();
            $table->string('source');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->useCurrent();
            $table->timestamps();

            $table->unique(['health_subject_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wearable_connections');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_type', 100);
            $table->string('purpose', 64);
            $table->json('actor_context');
            $table->char('subject_ref', 64)->nullable();
            $table->char('user_ref', 64)->nullable();
            $table->string('outcome', 16);
            $table->string('correlation_id', 64);
            $table->timestampTz('occurred_at');

            $table->index(['event_type', 'occurred_at']);
            $table->index('correlation_id');
        });

        DB::statement(
            'ALTER TABLE audit_events
                ADD CONSTRAINT audit_events_single_reference
                CHECK (subject_ref IS NULL OR user_ref IS NULL)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};

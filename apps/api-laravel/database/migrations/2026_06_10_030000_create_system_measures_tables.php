<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('exercise_type')->default('MOBILITY');
            $table->string('difficulty')->default('BEGINNER');
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->unsignedInteger('default_sets')->nullable();
            $table->unsignedInteger('default_repetitions')->nullable();
            $table->unsignedInteger('default_hold_seconds')->nullable();
            $table->text('instructions')->nullable();
            $table->text('safety_notes')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('default_feedback_prompt')->nullable();
            $table->boolean('requires_feedback')->default(true);
            $table->string('status')->default('ACTIVE');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('exercise_type');
            $table->index('status');

            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('system_exercise_tags', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category', 'key']);
        });

        Schema::create('system_exercise_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('system_exercise_id');
            $table->unsignedBigInteger('system_exercise_tag_id');

            $table->foreign('system_exercise_id')->references('id')->on('system_exercises')->onDelete('cascade');
            $table->foreign('system_exercise_tag_id')->references('id')->on('system_exercise_tags')->onDelete('cascade');
            $table->primary(['system_exercise_id', 'system_exercise_tag_id']);
        });

        Schema::create('system_measure_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->text('goal_summary')->nullable();
            $table->string('difficulty')->default('BEGINNER');
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->string('recommended_frequency')->nullable();
            $table->unsignedInteger('default_points')->nullable();
            $table->boolean('streak_enabled')->default(true);
            $table->boolean('requires_feedback')->default(true);
            $table->string('status')->default('ACTIVE');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('status');

            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('system_measure_template_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('system_measure_template_id');
            $table->unsignedBigInteger('system_exercise_id');
            $table->unsignedInteger('position');
            $table->boolean('is_required')->default(true);
            $table->string('custom_title')->nullable();
            $table->text('custom_instructions')->nullable();
            $table->unsignedInteger('custom_duration_minutes')->nullable();
            $table->unsignedInteger('custom_sets')->nullable();
            $table->unsignedInteger('custom_repetitions')->nullable();
            $table->unsignedInteger('custom_hold_seconds')->nullable();
            $table->text('custom_feedback_prompt')->nullable();
            $table->timestamps();

            $table->index('system_measure_template_id');
            $table->index('system_exercise_id');
            $table->unique(['system_measure_template_id', 'position']);

            $table->foreign('system_measure_template_id')->references('id')->on('system_measure_templates')->onDelete('cascade');
            $table->foreign('system_exercise_id')->references('id')->on('system_exercises')->onDelete('cascade');
        });

        Schema::create('user_system_measures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('source_system_measure_template_id')->nullable();
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('assignment_reason')->nullable();
            $table->jsonb('recommendation_context')->nullable();
            $table->string('status')->default('ASSIGNED');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('streak_enabled')->default(true);
            $table->boolean('points_enabled')->default(true);
            $table->unsignedInteger('points_per_completion')->nullable();
            $table->boolean('requires_feedback')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'status']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('source_system_measure_template_id')->references('id')->on('system_measure_templates')->onDelete('set null');
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('user_system_measure_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_system_measure_id');
            $table->unsignedBigInteger('source_system_exercise_id')->nullable();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('exercise_type');
            $table->string('difficulty')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('sets')->nullable();
            $table->unsignedInteger('repetitions')->nullable();
            $table->unsignedInteger('hold_seconds')->nullable();
            $table->text('instructions')->nullable();
            $table->text('safety_notes')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('feedback_prompt')->nullable();
            $table->boolean('requires_feedback')->default(true);
            $table->jsonb('tag_snapshot')->nullable();
            $table->string('status')->default('PENDING');
            $table->timestamps();

            $table->index('user_system_measure_id');
            $table->index('source_system_exercise_id');
            $table->unique(['user_system_measure_id', 'position']);

            $table->foreign('user_system_measure_id')->references('id')->on('user_system_measures')->onDelete('cascade');
            $table->foreign('source_system_exercise_id')->references('id')->on('system_exercises')->onDelete('set null');
        });

        // Completion feedback and pain/stress ratings are user-level health-adjacent data.
        // No company reporting endpoint exists in this slice. Future company aggregation
        // must use thresholds/suppression and must not expose feedback text or individual ratings.
        Schema::create('user_system_measure_exercise_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_system_measure_exercise_id');
            $table->timestamp('completed_at');
            $table->string('period_key')->nullable();
            $table->text('feedback_text')->nullable();
            $table->unsignedInteger('effort_rating')->nullable();
            $table->unsignedInteger('difficulty_rating')->nullable();
            $table->unsignedInteger('pain_before_rating')->nullable();
            $table->unsignedInteger('pain_after_rating')->nullable();
            $table->unsignedInteger('stress_before_rating')->nullable();
            $table->unsignedInteger('stress_after_rating')->nullable();
            $table->unsignedInteger('points_awarded')->nullable();
            $table->unsignedBigInteger('points_transaction_id')->nullable();
            $table->timestamps();

            $table->index('user_system_measure_exercise_id');

            $table->foreign('user_system_measure_exercise_id')->references('id')->on('user_system_measure_exercises')->onDelete('cascade');
            $table->foreign('points_transaction_id')->references('id')->on('point_transactions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_system_measure_exercise_completions');
        Schema::dropIfExists('user_system_measure_exercises');
        Schema::dropIfExists('user_system_measures');
        Schema::dropIfExists('system_measure_template_exercises');
        Schema::dropIfExists('system_measure_templates');
        Schema::dropIfExists('system_exercise_tag');
        Schema::dropIfExists('system_exercise_tags');
        Schema::dropIfExists('system_exercises');
    }
};

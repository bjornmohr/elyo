<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated identity-domain baseline (ELYO-104 / prompt 03).
 *
 * Reproduces the pre-restructure schema 1:1 by folding every historical
 * incremental migration into the initial CREATE statements. Columns that were
 * originally added by later ALTER migrations are appended at the end of their
 * table to match PostgreSQL column ordering (Postgres ignores Blueprint
 * `->after()`, so added columns always land last). Pure data-backfill steps
 * from the old migrations are intentionally omitted — a fresh baseline has no
 * rows to backfill.
 *
 * Runs on the `identity` connection (invoked with `--database=identity_migrator
 * --path=database/migrations/identity` by `php artisan elyo:migrate-fresh`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->integer('anonymity_threshold')->default(5);
            $table->unsignedBigInteger('created_by_elyo_admin_id')->nullable();
            $table->timestamps();
            // Folded from 2026_05_26_010000_add_team_layer_enabled_to_companies_table.
            $table->boolean('team_layer_enabled')->default(false);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('company_id');
            $table->string('status')->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'team_id']);
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('created_by_elyo_admin_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('role');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'role']);
        });

        Schema::create('invite_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('email');
            $table->string('role');
            $table->string('token_hash')->unique();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('invited_by_user_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            // Folded from 2026_05_26_000000_add_team_id_to_invite_tokens_table.
            $table->unsignedBigInteger('team_id')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('invited_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['company_id', 'team_id']);
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });

        Schema::create('wellbeing_entries', function (Blueprint $table) {
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

        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('DRAFT');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
            // Folded from 2026_05_17_120000_add_created_by_to_surveys_table.
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->string('text');
            $table->string('type')->default('SCALE');
            $table->integer('order');
            $table->boolean('is_required')->default(true);
            $table->jsonb('options')->nullable();
            $table->string('scale_min_label')->nullable();
            $table->string('scale_max_label')->nullable();
            $table->unsignedBigInteger('survey_id');
            $table->timestamps();

            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('cascade');
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->timestamp('submitted_at')->useCurrent();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('survey_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['user_id', 'survey_id']);
        });

        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('response_id');
            $table->unsignedBigInteger('question_id');
            $table->integer('scale_value')->nullable();
            $table->text('text_value')->nullable();
            $table->string('choice_value')->nullable();
            $table->boolean('bool_value')->nullable();
            $table->timestamps();

            $table->foreign('response_id')->references('id')->on('survey_responses')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('survey_questions')->onDelete('restrict');
        });

        Schema::create('survey_team', function (Blueprint $table) {
            $table->unsignedBigInteger('survey_id');
            $table->unsignedBigInteger('team_id');

            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->primary(['survey_id', 'team_id']);
        });

        Schema::create('anamnesis_profiles', function (Blueprint $table) {
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

        Schema::create('health_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->string('file_name');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_documents', function (Blueprint $table) {
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

        Schema::create('user_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->integer('total')->default(0);
            $table->string('level')->default('STARTER');
            $table->integer('streak')->default(0);
            $table->timestamp('last_checkin')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('points');
            $table->string('reason');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('name');
            $table->string('type');
            $table->jsonb('categories')->nullable();
            $table->text('description');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('minimum_level')->default('STARTER');
            $table->string('nachweis_url')->nullable();
            $table->string('verification_status')->default('PENDING_DOCS');
            $table->string('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_id')->nullable();
            $table->timestamps();

            $table->index('verification_status');
        });

        Schema::create('measures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('title');
            $table->string('category');
            $table->text('description');
            $table->string('status')->default('SUGGESTED');
            $table->timestamp('suggested_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            // Folded from 2026_06_10_000000_add_domain_fields_to_measures_table.
            $table->string('measure_origin')->default('COMPANY_CREATED');
            $table->string('delivery_type')->default('ONSITE');
            $table->string('execution_type')->default('EVENT_PARTICIPATION');
            $table->string('verification_requirement')->default('SELF_REPORT');
            $table->string('visibility_scope')->default('COMPANY');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('instructions')->nullable();
            $table->string('location_name')->nullable();
            $table->text('location_address')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('points_override')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
            $table->index(['company_id', 'status']);
        });

        Schema::create('wearable_connections', function (Blueprint $table) {
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

        Schema::create('wearable_syncs', function (Blueprint $table) {
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

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('endpoint')->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->boolean('checkin_reminder')->default(true);
            $table->string('checkin_reminder_time')->default('09:00');
            $table->boolean('weekly_summary')->default(true);
            $table->boolean('partner_updates')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('measure_participations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('measure_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamp('participated_at')->nullable();
            $table->timestamps();
            // Folded from 2026_06_10_010000_add_verification_fields_to_measure_participations_table.
            $table->string('verification_type')->default('SELF_REPORTED');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by_user_id')->nullable();

            $table->foreign('measure_id')->references('id')->on('measures')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->unique(['measure_id', 'user_id']);
            $table->index(['company_id', 'measure_id']);
            $table->index(['company_id', 'team_id', 'measure_id']);
            $table->foreign('verified_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('measure_checkin_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('measure_id');
            $table->unsignedBigInteger('company_id');
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('measure_id')->references('id')->on('measures')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('measure_id');
            $table->index('company_id');
            $table->index('valid_until');
        });

        // Postgres partial unique index: at most one active (non-revoked) token
        // per measure. Created via raw SQL because Blueprint's
        // `unique()->whereNull()` fluent is a silent no-op (it emits a *full*
        // unique index) — the pre-restructure migration carried that latent bug,
        // masked only because the old sqlite test lane used a different index and
        // skipped the constraint test. The Postgres-only lane (D9) requires the
        // real partial index so token rotation (revoke old → issue new) works.
        DB::statement(
            'CREATE UNIQUE INDEX measure_checkin_tokens_one_active_per_measure '
            .'ON measure_checkin_tokens (measure_id) WHERE revoked_at IS NULL'
        );

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
            // Folded from 2026_06_11_000000_add_category_and_is_featured_to_system_measure_templates_table.
            $table->string('category')->default('MIXED');
            $table->boolean('is_featured')->default(false);

            $table->index('status');
            $table->index('category');

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
            // Folded from 2026_06_11_010000_add_template_exercise_unique_to_system_measure_template_exercises_table.
            $table->unique(
                ['system_measure_template_id', 'system_exercise_id'],
                'system_measure_template_exercises_template_exercise_unique'
            );

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

        Schema::create('point_settings', function (Blueprint $table) {
            $table->id();
            $table->string('action')->unique();
            $table->integer('points');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_settings');
        Schema::dropIfExists('user_system_measure_exercise_completions');
        Schema::dropIfExists('user_system_measure_exercises');
        Schema::dropIfExists('user_system_measures');
        Schema::dropIfExists('system_measure_template_exercises');
        Schema::dropIfExists('system_measure_templates');
        Schema::dropIfExists('system_exercise_tag');
        Schema::dropIfExists('system_exercise_tags');
        Schema::dropIfExists('system_exercises');
        Schema::dropIfExists('measure_checkin_tokens');
        Schema::dropIfExists('measure_participations');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('wearable_syncs');
        Schema::dropIfExists('wearable_connections');
        Schema::dropIfExists('measures');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('user_points');
        Schema::dropIfExists('user_documents');
        Schema::dropIfExists('health_documents');
        Schema::dropIfExists('anamnesis_profiles');
        Schema::dropIfExists('survey_team');
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('wellbeing_entries');
        Schema::dropIfExists('invite_tokens');
        Schema::dropIfExists('user_roles');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['created_by_elyo_admin_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
        Schema::dropIfExists('teams');
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
    }
};

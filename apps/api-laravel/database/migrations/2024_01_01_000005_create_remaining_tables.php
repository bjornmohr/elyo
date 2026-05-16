<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('wearable_syncs');
        Schema::dropIfExists('wearable_connections');
        Schema::dropIfExists('measures');
        Schema::dropIfExists('partners');
    }
};

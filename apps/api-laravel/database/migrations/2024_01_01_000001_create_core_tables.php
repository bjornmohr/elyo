<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'team_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
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
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('email');
            $table->string('role');
            $table->string('token_hash')->unique();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('invited_by_user_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('invited_by_user_id')->references('id')->on('users')->onDelete('set null');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('wellbeing_entries');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
        Schema::dropIfExists('teams');
        Schema::dropIfExists('invite_tokens');
        Schema::dropIfExists('user_roles');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['created_by_elyo_admin_id']);
        });
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('industry')->nullable();
            $table->string('employee_range')->nullable();
            $table->string('country')->nullable();
            $table->string('checkin_frequency')->default('WEEKLY');
            $table->integer('anonymity_threshold')->default(5);
            $table->string('billing_email')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('role')->default('EMPLOYEE');
            $table->string('password_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('company_id')->nullable();
            $table->string('team_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->string('company_id');
            $table->string('manager_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });

        Schema::create('wellbeing_entries', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('mood');
            $table->integer('stress');
            $table->integer('energy');
            $table->double('score');
            $table->text('note')->nullable();
            $table->string('period_key');
            $table->string('company_id');
            $table->string('user_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['user_id', 'period_key']);
            $table->index(['company_id', 'period_key']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellbeing_entries');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
        Schema::dropIfExists('teams');
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
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
            $table->uuid('team_id');

            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->primary(['survey_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_team');
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};

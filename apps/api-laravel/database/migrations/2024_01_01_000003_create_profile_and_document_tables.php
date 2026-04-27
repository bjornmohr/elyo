<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamnesis_profiles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->unique();
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
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('type');
            $table->string('file_name');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_documents', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('file_name');
            $table->string('blob_url');
            $table->string('blob_key');
            $table->string('mime_type');
            $table->integer('size');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_documents');
        Schema::dropIfExists('health_documents');
        Schema::dropIfExists('anamnesis_profiles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measure_participations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('measure_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamp('participated_at')->nullable();
            $table->timestamps();

            $table->foreign('measure_id')->references('id')->on('measures')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->unique(['measure_id', 'user_id']);
            $table->index(['company_id', 'measure_id']);
            $table->index(['company_id', 'team_id', 'measure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_participations');
    }
};

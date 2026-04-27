<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_points', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->unique();
            $table->integer('total')->default(0);
            $table->string('level')->default('STARTER');
            $table->integer('streak')->default(0);
            $table->timestamp('last_checkin')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->integer('points');
            $table->string('reason');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('user_points');
    }
};

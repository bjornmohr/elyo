<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            if (config('database.default') === 'sqlite') {
                $table->unique(['measure_id', 'revoked_at'], 'measure_checkin_tokens_one_active_per_measure');
            } else {
                $table->unique(['measure_id'], 'measure_checkin_tokens_one_active_per_measure')
                    ->whereNull('revoked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_checkin_tokens');
    }
};

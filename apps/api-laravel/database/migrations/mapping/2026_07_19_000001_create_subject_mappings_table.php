<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_mappings', function (Blueprint $table): void {
            $table->id();
            $table->char('user_id_hmac', 64)->unique();
            $table->text('user_id_encrypted');
            $table->ulid('health_subject_id');
            $table->enum('status', ['ACTIVE', 'REVOKED'])->default('ACTIVE');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_mappings');
    }
};

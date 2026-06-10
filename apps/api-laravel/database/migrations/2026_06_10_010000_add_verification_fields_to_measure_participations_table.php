<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measure_participations', function (Blueprint $table) {
            $table->string('verification_type')->default('SELF_REPORTED');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('measure_participations')->update([
            'verification_type' => 'SELF_REPORTED',
            'verified_at' => DB::raw('participated_at'),
            'verified_by_user_id' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('measure_participations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn(['verification_type', 'verified_at']);
        });
    }
};

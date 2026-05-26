<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('company_id');
            $table->index(['company_id', 'team_id']);
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['company_id', 'team_id']);
            $table->dropColumn('team_id');
        });
    }
};

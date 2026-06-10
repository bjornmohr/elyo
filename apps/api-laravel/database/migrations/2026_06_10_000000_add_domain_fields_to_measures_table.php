<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measures', function (Blueprint $table) {
            $table->string('measure_origin')->default('COMPANY_CREATED');
            $table->string('delivery_type')->default('ONSITE');
            $table->string('execution_type')->default('EVENT_PARTICIPATION');
            $table->string('verification_requirement')->default('SELF_REPORT');
            $table->string('visibility_scope')->default('COMPANY');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('instructions')->nullable();
            $table->string('location_name')->nullable();
            $table->text('location_address')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('points_override')->nullable();
        });

        DB::table('measures')
            ->whereNotNull('team_id')
            ->update(['visibility_scope' => 'TEAM']);
    }

    public function down(): void
    {
        Schema::table('measures', function (Blueprint $table) {
            $table->dropColumn([
                'measure_origin',
                'delivery_type',
                'execution_type',
                'verification_requirement',
                'visibility_scope',
                'starts_at',
                'ends_at',
                'duration_minutes',
                'instructions',
                'location_name',
                'location_address',
                'capacity',
                'points_override',
            ]);
        });
    }
};

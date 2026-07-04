<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_measure_templates', function (Blueprint $table) {
            $table->string('target_signal')->nullable()->after('goal_summary');
            $table->text('assignment_reason_template')->nullable()->after('target_signal');
            $table->string('effect_metric')->nullable()->after('assignment_reason_template');
            $table->string('effect_metric_unit')->nullable()->after('effect_metric');
            $table->jsonb('location_tags')->nullable()->after('effect_metric_unit');
            $table->jsonb('posture_tags')->nullable()->after('location_tags');
            $table->boolean('requires_floor')->default(false)->after('posture_tags');
        });

        Schema::table('system_exercises', function (Blueprint $table) {
            $table->jsonb('steps')->nullable()->after('instructions');
            $table->string('main_pictogram_path')->nullable()->after('steps');
            $table->string('main_pictogram_alt')->nullable()->after('main_pictogram_path');
            $table->jsonb('location_tags')->nullable()->after('main_pictogram_alt');
            $table->jsonb('posture_tags')->nullable()->after('location_tags');
            // Nullable on purpose: null = inherit the template-level value.
            $table->boolean('requires_floor')->nullable()->after('posture_tags');
            $table->unsignedTinyInteger('default_effort')->nullable()->after('requires_floor');
        });
    }

    public function down(): void
    {
        Schema::table('system_measure_templates', function (Blueprint $table) {
            $table->dropColumn([
                'target_signal',
                'assignment_reason_template',
                'effect_metric',
                'effect_metric_unit',
                'location_tags',
                'posture_tags',
                'requires_floor',
            ]);
        });

        Schema::table('system_exercises', function (Blueprint $table) {
            $table->dropColumn([
                'steps',
                'main_pictogram_path',
                'main_pictogram_alt',
                'location_tags',
                'posture_tags',
                'requires_floor',
                'default_effort',
            ]);
        });
    }
};

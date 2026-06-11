<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('system_measure_template_exercises')
            ->select('system_measure_template_id', 'system_exercise_id')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy('system_measure_template_id', 'system_exercise_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new \RuntimeException(
                'Cannot add system_measure_template_exercises_template_exercise_unique while duplicate template/exercise pairs exist.'
            );
        }

        Schema::table('system_measure_template_exercises', function (Blueprint $table) {
            $table->unique(
                ['system_measure_template_id', 'system_exercise_id'],
                'system_measure_template_exercises_template_exercise_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('system_measure_template_exercises', function (Blueprint $table) {
            $table->dropUnique('system_measure_template_exercises_template_exercise_unique');
        });
    }
};

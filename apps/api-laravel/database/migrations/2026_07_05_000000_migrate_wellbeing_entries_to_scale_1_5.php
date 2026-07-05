<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Canonical wellbeing scale moves from 1-10 to 1-5 (mood, energy, stress).
 * Existing rows are mapped with CEIL(x/2) — pairs (1,2)->1 … (9,10)->5 —
 * and the derived score is recomputed with the 1-5 formula
 * (mood + (6 - stress) + energy) / 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE wellbeing_entries SET mood = CEIL(mood / 2.0), stress = CEIL(stress / 2.0), energy = CEIL(energy / 2.0)');
        DB::statement('UPDATE wellbeing_entries SET score = ROUND((mood + (6 - stress) + energy) / 3.0, 1)');
    }

    public function down(): void
    {
        // Lossy mapping — the original 1-10 values cannot be reconstructed.
        // Demo environments rebuild via migrate:fresh --seed.
    }
};

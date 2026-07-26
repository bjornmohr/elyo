<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned backend catalog for lab-marker metadata (ELYO-105).
 *
 * Names, units and orientation ranges are content candidates pending ELYO-94
 * review. No reading or identity data belongs in this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_markers', function (Blueprint $table): void {
            $table->string('marker_key')->primary();
            $table->string('name');
            $table->string('unit');
            $table->decimal('low', 12, 4)->nullable();
            $table->decimal('high', 12, 4)->nullable();
            // Column is `marker_group`, not `group`: GROUP is a reserved word in
            // Postgres and would only survive as a quoted identifier. The API
            // field stays `group` per ELYO-102 §1.1; the mapping lives in the
            // resource layer (prompt 12).
            $table->enum('marker_group', ['blutbild', 'immun', 'mikro', 'sonstige']);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_markers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('marker_key');
            $table->decimal('value', 8, 2);
            $table->string('status');
            $table->boolean('is_highlighted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'marker_key']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE lab_markers ADD CONSTRAINT lab_markers_status_check CHECK (status IN ('unter Bereich', 'im Orientierungsbereich', 'über Bereich'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_markers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_measure_templates', function (Blueprint $table) {
            $table->string('category')->default('MIXED');
            $table->boolean('is_featured')->default(false);

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('system_measure_templates', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'is_featured']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_measure_templates', function (Blueprint $table) {
            $table->string('category')->default('MIXED')->after('description');
            $table->boolean('is_featured')->default(false)->after('status');

            $table->index('category');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('system_measure_templates', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['is_featured']);
            $table->dropColumn(['category', 'is_featured']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'GRANT UPDATE (last_used_at, updated_at) '
            .'ON TABLE personal_access_tokens TO elyo_employee_rt'
        );
    }

    public function down(): void
    {
        DB::statement(
            'REVOKE UPDATE (last_used_at, updated_at) '
            .'ON TABLE personal_access_tokens FROM elyo_employee_rt'
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('charge_methods')
            ->where('applies_to', 'making')
            ->where('code', 'per_piece')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('charge_methods')
            ->where('applies_to', 'making')
            ->where('code', 'per_piece')
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};

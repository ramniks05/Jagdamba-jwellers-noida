<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_sequences')
            ->where('document_type', 'invoice')
            ->update([
                'prefix' => 'JJ',
                'separator' => '/',
                'padding' => 4,
                'reset_policy' => 'financial_year',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('document_sequences')
            ->where('document_type', 'invoice')
            ->update([
                'prefix' => 'INV',
                'separator' => '-',
                'updated_at' => now(),
            ]);
    }
};

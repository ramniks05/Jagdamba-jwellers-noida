<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_stock_movements', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        DB::table('metal_stock_movements')->whereNull('uuid')->orderBy('id')->lazy()->each(function (object $row): void {
            DB::table('metal_stock_movements')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('metal_stock_movements', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('making_mode', 20)->default('inside')->after('tax_amount');
            $table->decimal('making_amount', 14, 2)->default(0)->after('making_mode');
            $table->decimal('making_tax_percent', 8, 4)->default(0)->after('making_amount');
            $table->decimal('making_tax_amount', 14, 2)->default(0)->after('making_tax_percent');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['making_mode', 'making_amount', 'making_tax_percent', 'making_tax_amount']);
        });
    }
};

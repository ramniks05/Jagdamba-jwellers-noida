<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('previous_due', 14, 2)->default(0)->after('credit_amount');
            $table->unsignedSmallInteger('previous_bills')->default(0)->after('previous_due');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['previous_due', 'previous_bills']);
        });
    }
};

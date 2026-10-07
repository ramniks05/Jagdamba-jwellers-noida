<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_intakes', function (Blueprint $table) {
            $table->string('purpose', 20)->default('create');
        });
    }

    public function down(): void
    {
        Schema::table('customer_intakes', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};

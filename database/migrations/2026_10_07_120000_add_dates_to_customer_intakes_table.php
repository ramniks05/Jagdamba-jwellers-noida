<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_intakes', function (Blueprint $table) {
            $table->date('dob')->nullable();
            $table->date('anniversary')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_intakes', function (Blueprint $table) {
            $table->dropColumn(['dob', 'anniversary']);
        });
    }
};

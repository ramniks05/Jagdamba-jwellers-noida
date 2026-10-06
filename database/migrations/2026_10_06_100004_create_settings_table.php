<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope_key', 40);
            $table->string('group', 40);
            $table->string('key', 80);
            $table->text('value')->nullable();
            $table->string('value_type', 20);
            $table->timestamps();

            $table->unique(['company_id', 'scope_key', 'key']);
            $table->index(['company_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

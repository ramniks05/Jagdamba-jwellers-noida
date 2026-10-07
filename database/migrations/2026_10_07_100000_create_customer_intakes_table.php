<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('mobile', 20);
            $table->string('mobile_key', 15);
            $table->string('email', 160)->nullable();
            $table->string('address_line1', 200);
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postal_code', 12);
            $table->string('country', 100)->default('India');
            $table->string('pan', 10)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('status', 20);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'mobile_key'], 'intakes_company_status_mobile_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_intakes');
    }
};

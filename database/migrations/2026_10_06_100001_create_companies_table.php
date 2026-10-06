<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 20)->unique();
            $table->string('name', 160);
            $table->string('legal_name', 200)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('email', 160)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('website', 200)->nullable();
            $table->string('gstin', 15)->nullable()->unique();
            $table->string('pan', 10)->nullable()->unique();
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('status', 20)->default('trial')->index();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->char('currency_code', 3)->default('INR');
            $table->unsignedTinyInteger('fy_start_month')->default(4);
            $table->string('plan_code', 50)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

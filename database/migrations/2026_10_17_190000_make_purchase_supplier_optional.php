<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->supplierColumn('purchases', true);
        $this->supplierColumn('purchase_returns', true);
    }

    public function down(): void
    {
        $this->supplierColumn('purchase_returns', false);
        $this->supplierColumn('purchases', false);
    }

    private function supplierColumn(string $table, bool $nullable): void
    {
        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['supplier_id']);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($nullable): void {
            $column = $blueprint->unsignedBigInteger('supplier_id');
            if ($nullable) {
                $column->nullable();
            }
            $column->change();
            $blueprint->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });
    }
};

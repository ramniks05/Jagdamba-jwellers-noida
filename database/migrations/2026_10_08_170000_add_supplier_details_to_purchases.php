<?php

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('supplier_bill_number', 80)->nullable()->after('number');
        });

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->decimal('gross_weight', 12, 3)->default(0)->after('item_code');
            $table->decimal('wastage_percent', 8, 3)->default(0)->after('rate_per_gram');
            $table->decimal('labour_per_gram', 14, 2)->default(0)->after('wastage_percent');
            $table->decimal('stone_amount', 14, 2)->default(0)->after('labour_per_gram');
            $table->decimal('cost_amount', 14, 2)->default(0)->after('line_amount');
        });

        foreach (DB::table('purchases')->get(['id', 'total']) as $purchase) {
            $lines = DB::table('purchase_lines')->where('purchase_id', $purchase->id)->orderBy('id')->get(['id', 'item_id', 'line_amount']);
            $sum = $lines->reduce(fn (BigDecimal $sum, $line) => $sum->plus((string) $line->line_amount), BigDecimal::zero());
            $left = BigDecimal::of((string) $purchase->total);

            foreach ($lines as $index => $line) {
                $share = $index === $lines->count() - 1 || $sum->isZero()
                    ? $left
                    : BigDecimal::of((string) $purchase->total)->multipliedBy((string) $line->line_amount)->dividedBy($sum, 2, RoundingMode::HalfUp);
                $left = $left->minus($share);

                DB::table('purchase_lines')->where('id', $line->id)->update([
                    'cost_amount' => (string) $share->toScale(2, RoundingMode::HalfUp),
                    'gross_weight' => DB::table('items')->where('id', $line->item_id)->value('gross_weight') ?? 0,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn(['gross_weight', 'wastage_percent', 'labour_per_gram', 'stone_amount', 'cost_amount']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('supplier_bill_number');
        });
    }
};

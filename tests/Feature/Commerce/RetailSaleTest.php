<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\SaleRetailLine;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_retail_only_bill_with_inclusive_gst_and_manual_tax_amount(): void
    {
        $owner = $this->shopUser();
        $buyer = $this->counterCustomer($owner);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'retail_items' => [[
                'name' => 'Ganesh murti',
                'quantity' => '2',
                'unit_price' => '500',
                'tax_mode' => 'inclusive',
                'tax_amount' => '50',
            ]],
            'payments' => [
                ['method' => 'cash', 'amount' => '1000'],
            ],
        ])->assertRedirect();

        $line = SaleRetailLine::query()->sole();
        $this->assertSame('Ganesh murti', $line->name);
        $this->assertSame(2, $line->quantity);
        $this->assertSame('1000.00', (string) $line->line_amount);
        $this->assertSame('50.00', (string) $line->tax_amount);
        $this->assertSame('1000.00', (string) $line->sale->total);
    }

    public function test_mixed_jewellery_and_retail_bill_does_not_move_extra_stock(): void
    {
        $owner = $this->shopUser();
        $buyer = $this->counterCustomer($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ]);

        $this->actingAs($owner)->post(route('items.store'), $this->piece('MIX01', $gold, $purity))->assertRedirect();
        $item = Item::query()->where('item_code', 'MIX01')->firstOrFail();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '0',
            'making_mode' => 'inside',
            'retail_items' => [[
                'name' => 'Diya set',
                'quantity' => '1',
                'unit_price' => '200',
                'tax_mode' => 'none',
            ]],
            'payments' => [
                ['method' => 'cash', 'amount' => '999999'],
            ],
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(ItemStatus::Sold, $item->status);
        $this->assertSame(1, SaleRetailLine::query()->count());
        $sale = Sale::query()->with('retailLines')->latest('id')->firstOrFail();
        $this->assertSame('200.00', (string) $sale->retailLines->first()->line_amount);
        $this->assertTrue(BigDecimal::of((string) $sale->total)->isGreaterThan(BigDecimal::of('200')));
    }

    /**
     * @return array<string, mixed>
     */
    private function piece(string $code, MetalType $gold, Purity $purity): array
    {
        app(CompanyContext::class)->set(User::query()->where('company_id', $gold->company_id)->firstOrFail()->company);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'per_gram')->firstOrFail();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'percentage')->firstOrFail();
        $category = Category::query()->where('code', 'RING')->firstOrFail();

        return [
            'name' => 'Gold ring',
            'item_code' => $code,
            'sku' => $code,
            'category_uuid' => $category->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '10',
            'stone_weight' => '0',
            'other_weight' => '0',
            'making_method_uuid' => $making->uuid,
            'making_value' => '500',
            'wastage_method_uuid' => $wastage->uuid,
            'wastage_value' => '5',
            'stone_value' => '1000',
            'cost_price' => '60000',
            'selling_price' => '70000',
            'mrp' => '75000',
        ];
    }

    public function test_retail_product_master_can_be_saved(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)->post(route('retail-products.store'), [
            'code' => 'MURTI01',
            'name' => 'Ganesh murti',
            'default_unit_price' => '500',
            'default_tax_mode' => 'inclusive',
            'default_tax_percent' => '3',
            'is_active' => '1',
        ])->assertRedirect(route('retail-products.index'));

        $this->assertDatabaseHas('retail_products', [
            'code' => 'MURTI01',
            'name' => 'Ganesh murti',
        ]);
    }
}

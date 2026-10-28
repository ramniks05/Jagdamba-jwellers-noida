<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\SaleRetailLine;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BothBillTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_jewellery_tag_bill_and_other_item_bill_both_save_and_preview(): void
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
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('items.store'), $this->piece('E2ERING', $gold, $purity))->assertRedirect();
        $item = Item::query()->where('item_code', 'E2ERING')->firstOrFail();

        $soldAt = now(config('app.timezone'))->format('Y-m-d\TH:i');

        $jewelleryPreview = $this->actingAs($owner)->post(route('sales.preview'), [
            'customer_uuid' => $buyer->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '0',
            'making_mode' => 'inside',
            'sold_at' => $soldAt,
            'payments' => [['method' => 'cash', 'amount' => '50000']],
        ]);
        $jewelleryPreview->assertOk();
        $jewelleryPreview->assertSee('E2ERING', false);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '0',
            'making_mode' => 'inside',
            'sold_at' => $soldAt,
            'payments' => [['method' => 'cash', 'amount' => '50000']],
        ])->assertRedirect();

        $this->assertSame(ItemStatus::Sold, $item->fresh()->status);

        $retailPreview = $this->actingAs($owner)->post(route('sales.preview'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'sold_at' => $soldAt,
            'retail_items' => [[
                'name' => 'Brass diya',
                'quantity' => '2',
                'unit_price' => '120',
                'tax_mode' => 'none',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '240']],
        ]);
        $retailPreview->assertOk();
        $retailPreview->assertSee('Brass Diya', false);
        $retailPreview->assertSee('240.00', false);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'sold_at' => $soldAt,
            'retail_items' => [[
                'name' => 'Brass diya',
                'quantity' => '2',
                'unit_price' => '120',
                'tax_mode' => 'none',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '240']],
        ])->assertRedirect();

        $this->assertSame(1, SaleRetailLine::query()->where('name', 'Brass diya')->count());
        $retailSale = Sale::query()->whereHas('retailLines', fn ($q) => $q->where('name', 'Brass diya'))->firstOrFail();
        $this->assertSame('240.00', (string) $retailSale->total);
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
}

<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_returning_one_of_two_pieces_credits_only_that_piece(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '5000',
            'source' => 'manual',
        ])->assertRedirect();
        $this->seeShop($owner);
        $first = $this->piece($owner, $gold, $purity, 'RETA', '5000');
        $second = $this->piece($owner, $gold, $purity, 'RETB', '1000');
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $walkIn->uuid,
            'item_ids' => [$first->uuid, $second->uuid],
            'discount' => '0',
            'making_mode' => 'inside',
            'payments' => [['method' => 'cash', 'amount' => '109180']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('109180.00', (string) $sale->total);

        $firstLine = SaleLine::query()->where('item_id', $first->id)->firstOrFail();
        $this->actingAs($owner)->post(route('sales.returns.store', $sale), [
            'lines' => [$firstLine->uuid],
            'refund' => '56650',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $this->assertSame('56650.00', (string) SaleReturn::query()->latest('id')->firstOrFail()->amount);

        $secondLine = SaleLine::query()->where('item_id', $second->id)->firstOrFail();
        $this->actingAs($owner)->post(route('sales.returns.store', $sale), [
            'lines' => [$secondLine->uuid],
            'refund' => '52530',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $this->assertSame('52530.00', (string) SaleReturn::query()->latest('id')->firstOrFail()->amount);
    }

    private function piece(User $owner, MetalType $gold, Purity $purity, string $code, string $making): Item
    {
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $makingMethod = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail();

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Ring',
            'item_code' => $code,
            'sku' => $code,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '10',
            'stone_weight' => '0',
            'other_weight' => '0',
            'making_method_uuid' => $makingMethod->uuid,
            'making_value' => $making,
            'wastage_method_uuid' => $wastage->uuid,
            'wastage_value' => '0',
            'stone_value' => '0',
            'cost_price' => '0',
            'selling_price' => '0',
            'mrp' => '0',
        ])->assertRedirect();
        $this->seeShop($owner);

        return Item::query()->where('item_code', $code)->firstOrFail();
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

<?php

namespace Tests\Feature\Commerce;

use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Commerce\LedgerService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_cancel_a_mistaken_invoice_and_stock_returns(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->customer($owner);
        $item = $this->stockPiece($owner, $gold, $purity, 'CAN01');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'item_ids' => [$item->uuid],
            'payments' => [['method' => 'cash', 'amount' => '61800']],
        ])->assertRedirect();

        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('posted', $sale->status);
        $this->assertSame(ItemStatus::Sold, $item->refresh()->status);

        $this->actingAs($owner)->delete(route('sales.destroy', $sale), [
            'reason' => 'Wrong piece was scanned by mistake.',
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
        $this->assertSame('Wrong piece was scanned by mistake.', $sale->cancel_reason);
        $this->assertNotNull($sale->cancelled_at);
        $this->assertSame(ItemStatus::Available, $item->refresh()->status);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $buyer->id));

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('This invoice was cancelled')
            ->assertSee('Wrong piece was scanned by mistake.');
    }

    public function test_cancel_requires_a_reason_and_owner_permission(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $sale = $this->simpleSale($owner);

        $this->actingAs($owner)->delete(route('sales.destroy', $sale), [
            'reason' => 'short',
        ])->assertSessionHasErrors('reason');

        $manager = User::factory()->create(['company_id' => $owner->company_id]);
        app(\App\Services\Access\AccessProvisioner::class)->grant($manager, 'manager');

        $this->actingAs($manager)->delete(route('sales.destroy', $sale), [
            'reason' => 'Manager tried to cancel this bill.',
        ])->assertForbidden();
    }

    private function simpleSale(User $owner): Sale
    {
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->customer($owner);
        $item = $this->stockPiece($owner, $gold, $purity, 'CAN02');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'item_ids' => [$item->uuid],
            'payments' => [['method' => 'cash', 'amount' => '61800']],
        ])->assertRedirect();

        $this->seeShop($owner);

        return Sale::query()->latest('id')->firstOrFail();
    }

    private function stockPiece(User $owner, MetalType $gold, Purity $purity, string $code): Item
    {
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = \App\Models\ChargeMethod::query()->where('applies_to', \App\Enums\ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();
        $wastage = \App\Models\ChargeMethod::query()->where('applies_to', \App\Enums\ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

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
            'making_method_uuid' => $making->uuid,
            'making_value' => '0',
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

    private function customer(User $owner): Customer
    {
        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => 'Cancel Test Customer',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => '1',
        ])->assertRedirect();
        $this->seeShop($owner);

        return Customer::query()->where('name', 'Cancel Test Customer')->firstOrFail();
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

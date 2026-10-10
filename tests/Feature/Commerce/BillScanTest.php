<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Commerce\BillScanService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_scanned_barcode_finds_the_piece_with_its_bill_price(): void
    {
        $owner = $this->counter();
        $item = $this->piece($owner, 'G18-004', 'JJ0000123');
        $movements = InventoryTransaction::query()->count();

        $response = $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'JJ0000123']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('piece.uuid', $item->uuid)
            ->assertJsonPath('piece.code', 'G18-004')
            ->assertJsonPath('piece.barcode', 'JJ0000123')
            ->assertJsonPath('piece.purity', '22K')
            ->assertJsonPath('piece.metal', 'Gold 22K')
            ->assertJsonPath('piece.ready', true);

        $this->assertEquals(28160, $response->json('piece.line'));
        $this->assertEquals(27660, $response->json('piece.metalAmount'));
        $this->assertEquals(500, $response->json('piece.makingAmount'));

        $this->seeShop($owner);
        $this->assertSame(ItemStatus::Available, $item->fresh()->status);
        $this->assertSame($movements, InventoryTransaction::query()->count());
    }

    public function test_the_piece_code_works_too_and_scanner_suffixes_are_ignored(): void
    {
        $owner = $this->counter();
        $item = $this->piece($owner, 'G18-005');

        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'G18-005']))->assertOk()->assertJsonPath('piece.uuid', $item->uuid);
        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => "g18-005\r\n"]))->assertOk()->assertJsonPath('piece.uuid', $item->uuid);
        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => "G18-005\t"]))->assertOk()->assertJsonPath('piece.uuid', $item->uuid);
    }

    public function test_a_barcode_wins_over_another_piece_with_that_code(): void
    {
        $owner = $this->counter();
        $byCode = $this->piece($owner, 'TAG-77');
        $byBarcode = $this->piece($owner, 'R-100', 'TAG-77');

        $this->assertNotSame($byCode->uuid, $byBarcode->uuid);
        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'TAG-77']))->assertOk()->assertJsonPath('piece.uuid', $byBarcode->uuid);
    }

    public function test_an_unknown_barcode_is_reported_and_creates_nothing(): void
    {
        $owner = $this->counter();
        $this->piece($owner, 'G18-006');
        $items = Item::query()->count();

        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'NOPE-999']))
            ->assertNotFound()
            ->assertJsonPath('message', 'No piece in this shop has the code "NOPE-999".')
            ->assertJsonMissingPath('piece');
        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'two words']))->assertStatus(422)->assertJsonMissingPath('piece');
        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => '']))->assertStatus(422);

        $this->seeShop($owner);
        $this->assertSame($items, Item::query()->count());
    }

    public function test_sold_and_reserved_pieces_cannot_be_scanned_onto_a_bill(): void
    {
        $owner = $this->counter();
        $item = $this->piece($owner, 'G18-007', 'SOLD-1');
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('sales.store'), $this->sale($customer, [$item->uuid]))->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $this->assertSame(ItemStatus::Sold, $item->fresh()->status);

        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'SOLD-1']))
            ->assertStatus(409)
            ->assertJsonPath('message', 'G18-007 is sold, so it cannot be billed.')
            ->assertJsonMissingPath('piece');

        $reserved = $this->piece($owner, 'G18-008');
        $reserved->forceFill(['status' => ItemStatus::Reserved])->save();
        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'G18-008']))->assertStatus(409);
    }

    public function test_a_piece_without_todays_rate_is_refused_with_the_reason(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $this->piece($owner, 'G18-009');

        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'G18-009']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'G18-009: Enter a Gold 22K rate before billing this piece.');
    }

    public function test_barcodes_are_unique_in_a_shop_but_another_shop_may_reuse_one(): void
    {
        $owner = $this->counter();
        $this->piece($owner, 'G18-010', 'DUP-1');

        $this->actingAs($owner)->post(route('items.store'), $this->pieceData('G18-011', 'DUP-1'))->assertSessionHasErrors('barcode');

        $other = $this->otherShop();
        $this->seeShop($other);
        $this->actingAs($other)->post(route('items.store'), $this->pieceData('G18-011', 'DUP-1'))->assertSessionHasNoErrors();
    }

    public function test_two_scanned_pieces_bill_together_at_their_scanned_prices(): void
    {
        $owner = $this->counter();
        $first = $this->piece($owner, 'G18-012', 'SCAN-A');
        $second = $this->piece($owner, 'G18-013', 'SCAN-B');
        $customer = $this->customer($owner);

        $a = $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'SCAN-A']))->assertOk()->json('piece');
        $b = $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'SCAN-B']))->assertOk()->json('piece');

        $this->actingAs($owner)->post(route('sales.store'), $this->sale($customer, [$a['uuid'], $b['uuid']]))->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $sale = Sale::query()->with('lines')->sole();
        $this->assertCount(2, $sale->lines);
        $this->assertEquals($a['line'] + $b['line'], (float) $sale->lines->sum('line_amount'));
        $this->assertSame(ItemStatus::Sold, $first->fresh()->status);
        $this->assertSame(ItemStatus::Sold, $second->fresh()->status);

        $this->actingAs($owner)->getJson(route('sales.scan', ['code' => 'SCAN-A']))->assertStatus(409);
    }

    public function test_the_same_piece_twice_on_one_bill_is_refused(): void
    {
        $owner = $this->counter();
        $item = $this->piece($owner, 'G18-014');
        $customer = $this->customer($owner);

        $this->actingAs($owner)->post(route('sales.store'), $this->sale($customer, [$item->uuid, $item->uuid]))->assertSessionHasErrors();

        $this->seeShop($owner);
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(ItemStatus::Available, $item->fresh()->status);
    }

    public function test_scanning_needs_billing_rights_in_the_same_shop(): void
    {
        $owner = $this->counter();
        $this->piece($owner, 'G18-015', 'MINE-1');
        $this->app['auth']->forgetGuards();

        $this->getJson(route('sales.scan', ['code' => 'MINE-1']))->assertUnauthorized();

        $auditor = User::factory()->create(['company_id' => $owner->company_id, 'email' => 'auditor@jagdamba.test']);
        app(AccessProvisioner::class)->grant($auditor, 'auditor');
        $this->actingAs($auditor)->getJson(route('sales.scan', ['code' => 'MINE-1']))->assertForbidden();

        $cashier = User::factory()->create(['company_id' => $owner->company_id, 'email' => 'cashier@jagdamba.test']);
        app(AccessProvisioner::class)->grant($cashier, 'cashier');
        $this->actingAs($cashier)->getJson(route('sales.scan', ['code' => 'MINE-1']))->assertOk();

        $other = $this->otherShop();
        $this->actingAs($other)->getJson(route('sales.scan', ['code' => 'MINE-1']))->assertNotFound()->assertJsonMissingPath('piece');
    }

    public function test_the_bill_page_has_the_scan_box(): void
    {
        $owner = $this->counter();
        $this->piece($owner, 'G18-016', 'PAGE-1');

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('id="scan-code"', false)
            ->assertSee('Scan tag')
            ->assertSee(str_replace('/', '\/', route('sales.scan')), false)
            ->assertSee('G18-016');
    }

    public function test_scanner_control_characters_are_removed(): void
    {
        $this->assertSame('G18-004', BillScanService::clean("\x02G18-004\r\n"));
        $this->assertSame('', BillScanService::clean("\r\n"));
    }

    private function counter(): User
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => MetalType::query()->where('code', 'GOLD')->firstOrFail()->uuid,
            'purity_uuid' => Purity::query()->where('code', '22K')->firstOrFail()->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));
        $this->seeShop($owner);

        return $owner;
    }

    private function otherShop(): User
    {
        return $this->shopUser(['name' => 'Other Shop', 'legal_name' => 'Other Shop', 'code' => 'OTHER', 'email' => 'other@shop.test', 'gstin' => null, 'pan' => null]);
    }

    private function piece(User $owner, string $code, ?string $barcode = null): Item
    {
        $this->seeShop($owner);
        $this->actingAs($owner)->post(route('items.store'), $this->pieceData($code, $barcode))->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);

        return Item::query()->where('item_code', $code)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function pieceData(string $code, ?string $barcode = null): array
    {
        return [
            'name' => 'Ring',
            'item_code' => $code,
            'sku' => $code,
            'barcode' => $barcode,
            'metal_uuid' => MetalType::query()->where('code', 'GOLD')->firstOrFail()->uuid,
            'purity_uuid' => Purity::query()->where('code', '22K')->firstOrFail()->uuid,
            'location_uuid' => StockLocation::query()->where('code', 'MAIN')->firstOrFail()->uuid,
            'gross_weight' => '4.61',
            'stone_weight' => '0',
            'other_weight' => '0',
            'making_method_uuid' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail()->uuid,
            'making_value' => '500',
            'wastage_method_uuid' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail()->uuid,
            'wastage_value' => '0',
            'stone_value' => '0',
            'cost_price' => '0',
            'selling_price' => '0',
            'mrp' => '0',
        ];
    }

    private function customer(User $owner): Customer
    {
        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => 'Meera Shah',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => '1',
        ])->assertRedirect();
        $this->seeShop($owner);

        return Customer::query()->where('name', 'Meera Shah')->firstOrFail();
    }

    /**
     * @param  list<string>  $uuids
     * @return array<string, mixed>
     */
    private function sale(Customer $customer, array $uuids): array
    {
        return [
            'customer_uuid' => $customer->uuid,
            'item_ids' => $uuids,
            'discount' => '0',
            'making_mode' => 'inside',
            'payments' => [],
        ];
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

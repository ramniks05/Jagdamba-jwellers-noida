<?php

namespace Tests\Feature\Commerce;

use App\Enums\LedgerDirection;
use App\Enums\MetalStockKind;
use App\Enums\PartyType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\MetalStockMovement;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Commerce\MetalStockService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetalStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_grams_are_added_by_purity_and_a_bill_takes_the_net_weight_off(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $lighter = Purity::query()->where('code', '18K')->firstOrFail();
        $silver = Purity::query()->where('code', '999')->firstOrFail();
        $branch = StockLocation::query()->where('code', 'MAIN')->firstOrFail()->branch;
        $walkIn = Customer::query()->where('code', 'WALKIN')->firstOrFail();

        $this->actingAs($owner)->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('Add Purchase to stock')
            ->assertSee('Purchase')
            ->assertSee('weight times the rate, then GST, is the total')
            ->assertSee('Rate / g')
            ->assertSee('Others');

        $this->actingAs($owner)->post(route('reports.stock.store'), [
            'kind' => 'opening',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $silver->uuid,
            'grams' => '10',
        ])->assertSessionHasErrors('purity_uuid');

        $this->stock($owner, $branch, $gold, $purity, 'opening', '500');
        $this->stock($owner, $branch, $gold, $purity, 'opening', '480');
        $this->seeShop($owner);
        $this->assertSame(1, MetalStockMovement::query()->where('kind', MetalStockKind::Opening)->where('purity_id', $purity->id)->count());
        $this->assertSame('480.000', $this->balance('22K'));

        $this->actingAs($owner)->post(route('reports.stock.store'), [
            'kind' => 'purchase',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => '10',
        ])->assertSessionHasErrors('supplier_uuid');

        $this->stock($owner, $branch, $gold, $purity, 'purchase', '100');
        $this->seeShop($owner);
        $bought = MetalStockMovement::query()->where('kind', MetalStockKind::Purchase)->where('purity_id', $purity->id)->firstOrFail();
        $this->assertSame(Supplier::class, $bought->reference_type);
        $this->stock($owner, $branch, $gold, $lighter, 'purchase', '100');
        $this->seeShop($owner);
        $this->assertSame('580.000', $this->balance('22K'));
        $this->assertSame('100.000', $this->balance('18K'));

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $walkIn->uuid,
            'discount' => '0',
            'new_pieces' => [[
                'name' => 'Loose bangle',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => StockLocation::query()->where('code', 'MAIN')->firstOrFail()->uuid,
                'gross_weight' => '10',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
            ]],
            'payments' => [
                ['method' => 'cash', 'amount' => '61800'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $this->assertSame('570.000', $this->balance('22K'));
        $this->assertSame('10.000', $this->sold('22K'));

        $this->actingAs($owner)->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('570.000')
            ->assertSee('18K')
            ->assertSee('100.000')
            ->assertSee('For information. The full list is in the Stock report.')
            ->assertDontSee('Stock on hand');

        $sale = Sale::query()->latest('id')->firstOrFail();
        $line = SaleLine::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->actingAs($owner)->post(route('sales.returns.store', $sale), [
            'lines' => [$line->uuid],
            'refund' => '61800',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $this->assertSame('580.000', $this->balance('22K'));
        $this->assertSame('0.000', $this->sold('22K'));

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $walkIn->uuid,
            'discount' => '0',
            'new_pieces' => [[
                'name' => 'Own bangle',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => StockLocation::query()->where('code', 'MAIN')->firstOrFail()->uuid,
                'gross_weight' => '4',
                'own' => '1',
            ]],
            'payments' => [
                ['method' => 'cash', 'amount' => '24720'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $this->assertSame('580.000', $this->balance('22K'));
        $this->assertSame('0.000', $this->sold('22K'));
        $own = Sale::query()->latest('id')->firstOrFail();
        $ownLine = SaleLine::query()->where('sale_id', $own->id)->firstOrFail();
        $this->assertTrue($ownLine->item->own_gold);
        $this->assertSame('7113', $ownLine->hsn_code);
        $this->actingAs($owner)->get(route('reports.own-gold'))
            ->assertOk()
            ->assertSee('Own bangle')
            ->assertSee('7113');
        $this->actingAs($owner)->get(route('reports.stock'))
            ->assertOk()
            ->assertDontSee('Own bangle');
        $this->actingAs($owner)->post(route('sales.returns.store', $own), [
            'lines' => [$ownLine->uuid],
            'refund' => '24720',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $this->assertSame('580.000', $this->balance('22K'));

        $cashier = User::factory()->create(['company_id' => $owner->company_id, 'email' => 'stock-cashier@jagdamba.test']);
        $this->seeShop($owner);
        app(AccessProvisioner::class)->grant($cashier, 'cashier');
        $this->actingAs($cashier)->post(route('reports.stock.store'), [
            'kind' => 'purchase',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => '5',
        ])->assertForbidden();
    }

    public function test_a_supplier_filter_shows_grams_bought_from_that_supplier(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $branch = StockLocation::query()->where('code', 'MAIN')->firstOrFail()->branch;
        $first = $this->makeSupplier($owner, 'STK01', 'Stock Supplier');
        $second = $this->makeSupplier($owner, 'STK02', 'Other Supplier');

        $this->stock($owner, $branch, $gold, $purity, 'opening', '100');
        $this->actingAs($owner)->post(route('reports.stock.store'), [
            'kind' => 'purchase',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => '5',
            'rate_per_gram' => '6000',
            'supplier_uuid' => $first->uuid,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('reports.stock.store'), [
            'kind' => 'purchase',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => '7',
            'rate_per_gram' => '6000',
            'supplier_uuid' => $second->uuid,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $this->assertSame('12.000', $this->row('22K')['purchased']);
        $filtered = app(MetalStockService::class)->balances($first)->firstWhere('purity', '22K');
        $this->assertSame('5.000', $filtered['purchased']);
        $this->assertSame('112.000', $filtered['balance']);

        $this->actingAs($owner)->get(route('reports.stock', ['supplier' => $first->uuid]))
            ->assertOk()
            ->assertSee('All suppliers')
            ->assertSee('Purchased grams are from Stock Supplier')
            ->assertSee('5.000 g')
            ->assertSee('112.000 g');
    }

    public function test_a_purchase_prices_the_grams_and_adds_gst(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '18K')->firstOrFail();
        $branch = StockLocation::query()->where('code', 'MAIN')->firstOrFail()->branch;
        $supplier = $this->makeSupplier($owner, 'STK01', 'Stock Supplier');

        $this->actingAs($owner)->post(route('reports.stock.store'), [
            'kind' => 'purchase',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => '2',
            'supplier_uuid' => $supplier->uuid,
        ])->assertSessionHasErrors('rate_per_gram');

        $this->actingAs($owner)->post(route('reports.stock.store'), [
            'kind' => 'purchase',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => '2',
            'rate_per_gram' => '10000',
            'supplier_uuid' => $supplier->uuid,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $bought = MetalStockMovement::query()->where('kind', MetalStockKind::Purchase)->firstOrFail();
        $this->assertSame('2.000', (string) $bought->grams);
        $this->assertSame('10000.00', (string) $bought->rate_per_gram);
        $this->assertSame('20000.00', (string) $bought->taxable_amount);
        $this->assertSame('3.00', (string) $bought->tax_percent);
        $this->assertSame('600.00', (string) $bought->tax_amount);
        $this->assertSame('20600.00', (string) $bought->total_amount);
        $entry = LedgerEntry::query()->where('party_type', PartyType::Supplier)->where('party_id', $supplier->id)->firstOrFail();
        $this->assertSame(LedgerDirection::Credit, $entry->direction);
        $this->assertSame('20600.00', (string) $entry->amount);
        $this->assertSame('2.000', $this->balance('18K'));
    }

    public function test_a_purchase_can_be_corrected(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '18K')->firstOrFail();
        $other = Purity::query()->where('code', '14K')->firstOrFail();
        $branch = StockLocation::query()->where('code', 'MAIN')->firstOrFail()->branch;
        $first = $this->makeSupplier($owner, 'STK01', 'Stock Supplier');
        $second = $this->makeSupplier($owner, 'STK02', 'Other Supplier');

        $this->stock($owner, $branch, $gold, $purity, 'purchase', '2');
        $this->seeShop($owner);
        $bought = MetalStockMovement::query()->where('kind', MetalStockKind::Purchase)->firstOrFail();

        $this->actingAs($owner)->get(route('reports.stock', ['edit' => $bought->uuid]))
            ->assertOk()
            ->assertSee('Correct purchase')
            ->assertSee('Save correction')
            ->assertSee('Stock Supplier');
        $this->actingAs($owner)->get(route('reports.stock', ['report' => 1]))
            ->assertOk()
            ->assertDontSee('Correct purchase');
        $this->actingAs($owner)->get(route('reports.stock', ['grams' => 1]))
            ->assertOk()
            ->assertSee('Gram stock report')
            ->assertSee('2.000')
            ->assertDontSee('Add Purchase to stock')
            ->assertDontSee('Stock on hand');

        $this->actingAs($owner)->put(route('reports.stock.update', $bought), [
            'kind' => 'purchase',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $other->uuid,
            'grams' => '3',
            'rate_per_gram' => '8000',
            'supplier_uuid' => $second->uuid,
        ])->assertRedirect(route('reports.stock'))->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $bought->refresh();
        $this->assertSame($other->id, $bought->purity_id);
        $this->assertSame($second->id, $bought->reference_id);
        $this->assertSame('3.000', (string) $bought->grams);
        $this->assertSame('8000.00', (string) $bought->rate_per_gram);
        $this->assertSame('24000.00', (string) $bought->taxable_amount);
        $this->assertSame('720.00', (string) $bought->tax_amount);
        $this->assertSame('24720.00', (string) $bought->total_amount);
        $this->assertNull(app(MetalStockService::class)->balances()->firstWhere('purity', '18K'));
        $this->assertSame('3.000', $this->balance('14K'));
        $this->assertSame(0, LedgerEntry::query()->where('party_id', $first->id)->count());
        $moved = LedgerEntry::query()->where('party_type', PartyType::Supplier)->where('party_id', $second->id)->firstOrFail();
        $this->assertSame(LedgerDirection::Credit, $moved->direction);
        $this->assertSame('24720.00', (string) $moved->amount);

        $sale = MetalStockMovement::query()->create([
            'branch_id' => $branch->id,
            'metal_type_id' => $gold->id,
            'purity_id' => $purity->id,
            'kind' => MetalStockKind::Sale,
            'grams' => '1.000',
            'occurred_at' => now(),
        ]);
        $this->actingAs($owner)->put(route('reports.stock.update', $sale), [
            'kind' => 'purchase',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => '1',
            'rate_per_gram' => '1000',
            'supplier_uuid' => $second->uuid,
        ])->assertNotFound();
    }

    private function makeSupplier(User $owner, string $code, string $name): Supplier
    {
        $this->actingAs($owner)->post(route('suppliers.store'), [
            'code' => $code,
            'name' => $name,
            'kyc_status' => 'pending',
            'is_active' => '1',
        ])->assertRedirect();
        $this->seeShop($owner);

        return Supplier::query()->where('code', $code)->firstOrFail();
    }

    private function stock(User $owner, Branch $branch, MetalType $metal, Purity $purity, string $kind, string $grams): void
    {
        $payload = [
            'kind' => $kind,
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $metal->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => $grams,
        ];

        if ($kind === 'purchase') {
            $supplier = Supplier::query()->where('code', 'STK01')->first();

            if (! $supplier) {
                $this->actingAs($owner)->post(route('suppliers.store'), [
                    'code' => 'STK01',
                    'name' => 'Stock Supplier',
                    'kyc_status' => 'pending',
                    'is_active' => '1',
                ])->assertRedirect();
                $this->seeShop($owner);
                $supplier = Supplier::query()->where('code', 'STK01')->firstOrFail();
            }

            $payload['supplier_uuid'] = $supplier->uuid;
            $payload['rate_per_gram'] = '6000';
        }

        $this->actingAs($owner)->post(route('reports.stock.store'), $payload)
            ->assertRedirect(route('reports.stock'))
            ->assertSessionHasNoErrors();
    }

    private function balance(string $purity): string
    {
        return $this->row($purity)['balance'];
    }

    private function sold(string $purity): string
    {
        return $this->row($purity)['sold'];
    }

    /**
     * @return array{metal: string, purity: string, opening: string, purchased: string, sold: string, balance: string, short: bool}
     */
    private function row(string $purity): array
    {
        $row = app(MetalStockService::class)->balances()->firstWhere('purity', $purity);

        $this->assertNotNull($row);

        return $row;
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

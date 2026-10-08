<?php

namespace Tests\Feature\Commerce;

use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Models\ChargeMethod;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purchase;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Commerce\LedgerService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_supplier_bill_brings_in_many_pieces_with_their_own_selling_making(): void
    {
        $owner = $this->shopUser();
        $supplier = $this->supplier($owner);
        [$gold, $purity, $location] = $this->basics($owner);
        $perGram = ChargeMethod::query()->where('applies_to', 'making')->where('code', 'per_gram')->firstOrFail();
        $percent = ChargeMethod::query()->where('applies_to', 'wastage')->where('code', 'percentage')->firstOrFail();

        $this->actingAs($owner)->get(route('purchases.create', ['supplier' => $supplier->uuid]))
            ->assertOk()
            ->assertSee('"nextCode":"PC0001"', false)
            ->assertSee('value="'.$supplier->uuid.'" data-payable="0.00" selected', false);

        $this->actingAs($owner)->post(route('purchases.store'), $this->bill($supplier, $location, [
            'supplier_bill_number' => 'MB/221',
            'discount' => '390',
            'payments' => [['method' => 'cash', 'amount' => '50000'], ['method' => 'bank', 'amount' => '']],
            'lines' => [
                $this->line($gold, $purity, 'PC0001', [
                    'name' => 'Gold Necklace',
                    'gross_weight' => '10',
                    'stone_weight' => '0.5',
                    'rate_per_gram' => '6000',
                    'wastage_percent' => '2',
                    'labour_per_gram' => '500',
                    'stone_value' => '1000',
                    'making_method_uuid' => $perGram->uuid,
                    'making_value' => '800',
                    'wastage_method_uuid' => $percent->uuid,
                    'wastage_value' => '1.5',
                    'huid' => 'ab12cd',
                ]),
                $this->line($gold, $purity, 'pc0002', [
                    'gross_weight' => '5',
                    'rate_per_gram' => '6000',
                    'labour_per_gram' => '300',
                ]),
                ['name' => '', 'gross_weight' => '', 'amount' => ''],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->seeShop($owner);
        $purchase = Purchase::query()->with('lines')->firstOrFail();
        $this->assertSame('MB/221', $purchase->supplier_bill_number);
        $this->assertSame('95390.00', (string) $purchase->lines_amount);
        $this->assertSame('2850.00', (string) $purchase->tax_amount);
        $this->assertSame('97850.00', (string) $purchase->total);
        $this->assertSame('50000.00', (string) $purchase->paid_amount);
        $this->assertSame('47850.00', $purchase->dueAmount());
        $this->assertCount(2, $purchase->lines);
        $this->assertSame('63890.00', (string) $purchase->lines[0]->line_amount);
        $this->assertSame('65537.65', (string) $purchase->lines[0]->cost_amount);
        $this->assertSame('32312.35', (string) $purchase->lines[1]->cost_amount);

        $necklace = Item::query()->where('item_code', 'PC0001')->firstOrFail();
        $this->assertSame('9.500', (string) $necklace->net_weight);
        $this->assertSame('65537.65', (string) $necklace->cost_price);
        $this->assertSame((int) $perGram->id, (int) $necklace->making_method_id);
        $this->assertSame('800.0000', (string) $necklace->making_value);
        $this->assertSame('AB12CD', $necklace->huid);
        $this->assertSame('1000.00', (string) $necklace->stone_value);
        $this->assertTrue(Item::query()->where('item_code', 'PC0002')->exists());
        $this->assertSame('-47850.00', app(LedgerService::class)->balance(PartyType::Supplier, (int) $supplier->id));

        $this->actingAs($owner)->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee('their bill MB/221')
            ->assertSee('Part paid')
            ->assertSee('Send ticked pieces back')
            ->assertSee('wastage 2%');

        $chain = $purchase->lines[1];
        $this->actingAs($owner)->post(route('purchases.return', $purchase), ['lines' => [$chain->uuid]])->assertRedirect();
        $this->seeShop($owner);
        $this->assertSame(ItemStatus::SentBack, Item::query()->where('item_code', 'PC0002')->firstOrFail()->status);
        $this->assertSame(ItemStatus::Available, $necklace->refresh()->status);
        $purchase->refresh();
        $this->assertSame('32312.35', $purchase->returnedAmount());
        $this->assertSame('15537.65', $purchase->dueAmount());
        $this->assertSame('-15537.65', app(LedgerService::class)->balance(PartyType::Supplier, (int) $supplier->id));

        $this->actingAs($owner)->post(route('purchases.return', $purchase), ['lines' => [$chain->uuid]])->assertSessionHasErrors('lines');
        $this->seeShop($owner);

        $this->actingAs($owner)->post(route('purchases.payments.store', $purchase), ['method' => 'upi', 'amount' => '20000'])->assertSessionHasErrors('amount');
        $this->seeShop($owner);
        $this->actingAs($owner)->post(route('purchases.payments.store', $purchase), ['method' => 'upi', 'amount' => '15537.65', 'reference' => 'UTR1'])->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $purchase->refresh();
        $this->assertSame('0.00', $purchase->dueAmount());
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Supplier, (int) $supplier->id));

        $this->actingAs($owner)->get(route('purchases.show', $purchase))->assertOk()->assertSee('Paid')->assertSee('UTR1');
        $this->actingAs($owner)->get(route('purchases.index', ['show' => 'paid']))->assertOk()->assertSee($purchase->number);
        $this->actingAs($owner)->get(route('purchases.index', ['show' => 'due']))->assertOk()->assertDontSee($purchase->number);
        $this->actingAs($owner)->get(route('purchases.index', ['search' => 'MB/221']))->assertOk()->assertSee($purchase->number);
    }

    public function test_amount_bills_and_supplier_payments_settle_the_oldest_bill_first(): void
    {
        $owner = $this->shopUser();
        $supplier = $this->supplier($owner);
        [$gold, $purity, $location] = $this->basics($owner);

        foreach (['PC0001', 'PC0002'] as $code) {
            $this->actingAs($owner)->post(route('purchases.store'), $this->bill($supplier, $location, [
                'pricing' => 'amount',
                'gst_percent' => '0',
                'lines' => [$this->line($gold, $purity, $code, ['gross_weight' => '2', 'amount' => '10000'])],
            ]))->assertSessionHasNoErrors();
            $this->seeShop($owner);
        }

        [$first, $second] = Purchase::query()->orderBy('id')->get()->all();
        $this->assertSame('10000.00', (string) $first->total);
        $this->assertSame('5000.00', (string) $first->lines()->firstOrFail()->rate_per_gram);

        $this->actingAs($owner)->post(route('suppliers.payments.store', $supplier), ['method' => 'cash', 'amount' => '15000'])->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $this->assertSame('0.00', $first->refresh()->dueAmount());
        $this->assertSame('5000.00', $second->refresh()->dueAmount());
        $this->actingAs($owner)->get(route('purchases.show', $first))->assertOk()->assertSee('was paid from the supplier account');
    }

    public function test_a_bill_with_repeated_or_used_codes_or_no_rate_is_refused(): void
    {
        $owner = $this->shopUser();
        $supplier = $this->supplier($owner);
        [$gold, $purity, $location] = $this->basics($owner);

        $this->actingAs($owner)->post(route('purchases.store'), $this->bill($supplier, $location, [
            'lines' => [
                $this->line($gold, $purity, 'PC0001', ['gross_weight' => '2', 'rate_per_gram' => '6000']),
                $this->line($gold, $purity, 'PC0001', ['gross_weight' => '3', 'rate_per_gram' => '']),
            ],
        ]))->assertSessionHasErrors(['lines.1.item_code', 'lines.1.rate_per_gram']);

        $this->seeShop($owner);
        $this->assertSame(0, Purchase::query()->count());

        $this->actingAs($owner)->post(route('purchases.store'), $this->bill($supplier, $location, [
            'lines' => [$this->line($gold, $purity, 'PC0001', ['gross_weight' => '2', 'stone_weight' => '2', 'rate_per_gram' => '6000'])],
        ]))->assertSessionHasErrors('lines.0.gross_weight');
    }

    public function test_a_new_supplier_from_the_purchase_page_comes_back_to_it(): void
    {
        $owner = $this->shopUser();

        $this->actingAs($owner)->get(route('suppliers.create', ['for' => 'purchase']))->assertOk()->assertSee('name="for" value="purchase"', false);
        $response = $this->actingAs($owner)->post(route('suppliers.store'), [
            'code' => 'SUP09',
            'name' => 'Zaveri Bazaar Traders',
            'kyc_status' => 'pending',
            'is_active' => '1',
            'for' => 'purchase',
        ]);

        $this->seeShop($owner);
        $supplier = Supplier::query()->where('code', 'SUP09')->firstOrFail();
        $response->assertRedirect(route('purchases.create', ['supplier' => $supplier->uuid]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bill(Supplier $supplier, StockLocation $location, array $overrides): array
    {
        return array_merge([
            'supplier_uuid' => $supplier->uuid,
            'purchased_on' => now()->toDateString(),
            'location_uuid' => $location->uuid,
            'pricing' => 'rate',
            'gst_percent' => '3',
            'discount' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function line(MetalType $gold, Purity $purity, string $code, array $overrides): array
    {
        return array_merge([
            'name' => 'Gold Chain',
            'item_code' => $code,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
        ], $overrides);
    }

    private function supplier(User $owner): Supplier
    {
        $this->actingAs($owner)->post(route('suppliers.store'), [
            'code' => 'SUP01',
            'name' => 'Mumbai Bullion',
            'kyc_status' => 'pending',
            'is_active' => '1',
        ])->assertRedirect();
        $this->seeShop($owner);

        return Supplier::query()->where('code', 'SUP01')->firstOrFail();
    }

    /**
     * @return array{0: MetalType, 1: Purity, 2: StockLocation}
     */
    private function basics(User $owner): array
    {
        $this->seeShop($owner);

        return [
            MetalType::query()->where('code', 'GOLD')->firstOrFail(),
            Purity::query()->where('code', '22K')->firstOrFail(),
            StockLocation::query()->where('code', 'MAIN')->firstOrFail(),
        ];
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

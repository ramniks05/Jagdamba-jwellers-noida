<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Enums\PartyType;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Commerce\LedgerService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_piece_is_billed_from_the_rate_saved_at_that_moment(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->counterCustomer($owner);

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->actingAs($owner)->post(route('items.store'), $this->piece('RING01', $gold, $purity))->assertRedirect();

        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'RING01')->firstOrFail();
        $this->assertSame(ItemStatus::Available, $item->status);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => Customer::query()->where('code', 'WALKIN')->firstOrFail()->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '500',
            'making_mode' => 'inside',
            'payments' => [
                ['method' => 'cash', 'amount' => '70555'],
            ],
        ])->assertSessionHasErrors(['customer_uuid' => 'Choose the customer by name or mobile number, or add them as a new customer.']);
        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertDontSee('"walkin":true', false);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'item_ids' => [$item->uuid],
            'discount' => '500',
            'making_mode' => 'inside',
            'payments' => [
                ['method' => 'cash', 'amount' => '20000'],
                ['method' => 'upi', 'amount' => '50465', 'reference' => 'UPI123'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $item->refresh();
        $this->assertSame(ItemStatus::Sold, $item->status);
        $line = SaleLine::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame('6000.00', (string) $line->rate_per_gram);
        $this->assertSame('70465.00', (string) $line->sale->total);
        $this->assertSame('3000.00', (string) $line->sale->hallmark_amount);

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6100',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->seeShop($owner);
        $this->assertSame(2, MetalRate::query()->count());
        $this->assertSame('6000.00', (string) $line->fresh()->rate_per_gram);

        $this->actingAs($owner)
            ->get(route('sales.show', $line->sale))
            ->assertOk()
            ->assertSee('70,465.00')
            ->assertSee('Hallmark (no GST)')
            ->assertSee('Pieces total')
            ->assertSee('Discount')
            ->assertSee('UPI123');

        $this->actingAs($owner)->post(route('items.store'), $this->piece('RING02', $gold, $purity))->assertRedirect();
        $this->seeShop($owner);
        $second = Item::query()->where('item_code', 'RING02')->firstOrFail();

        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => 'Meera Shah',
            'mobile' => '9876500000',
            'kyc_status' => 'pending',
            'customer_type' => 'retail',
            'is_active' => '1',
        ])->assertRedirect();

        $this->seeShop($owner);
        $customer = Customer::query()->where('name', 'Meera Shah')->firstOrFail();
        $this->assertSame('CUS0002', $customer->code);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'item_ids' => [$second->uuid],
            'discount' => '500',
            'making_mode' => 'inside',
            'payments' => [
                ['method' => 'cash', 'amount' => '10000'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $this->assertSame('61545.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));

        $this->actingAs($owner)
            ->get(route('reports.outstanding'))
            ->assertOk()
            ->assertSee('Meera Shah');

        $this->actingAs($owner)->post(route('customers.payments.store', $customer), [
            'method' => 'upi',
            'amount' => '61545',
            'reference' => 'SETTLE',
        ])->assertRedirect(route('customers.show', $customer));

        $this->seeShop($owner);
        $this->assertSame('0.00', app(LedgerService::class)->balance(PartyType::Customer, (int) $customer->id));
    }

    public function test_a_bill_can_use_a_rate_other_than_today(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->counterCustomer($owner);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '500',
            'new_pieces' => [[
                'name' => 'Rate check',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '2',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
                'rate_per_gram' => '5500',
            ]],
            'payments' => [
                ['method' => 'cash', 'amount' => '10815'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $line = SaleLine::query()->where('name', 'Rate check')->firstOrFail();
        $this->assertSame('5500.00', (string) $line->rate_per_gram);
        $this->assertSame('11000.00', (string) $line->metal_amount);
        $this->actingAs($owner)->get(route('sales.show', $line->sale))
            ->assertOk()
            ->assertSee('Discount')
            ->assertSee('10,815.00');
    }

    public function test_a_cashier_can_see_pieces_but_cannot_add_one(): void
    {
        $owner = $this->shopUser();
        $cashier = User::factory()->create([
            'company_id' => $owner->company_id,
            'email' => 'cashier@jagdamba.test',
        ]);
        $this->seeShop($owner);
        app(AccessProvisioner::class)->grant($cashier, 'cashier');

        $this->actingAs($cashier)->get(route('items.index'))->assertOk();
        $this->actingAs($cashier)->post(route('items.store'), [])->assertForbidden();
        $this->actingAs($cashier)->get(route('sales.create'))->assertOk()->assertSee('Find customer')->assertSee('Weigh and bill')->assertSee('Gross g')->assertSee('Own');
    }

    public function test_the_bill_screen_shows_the_price_and_a_new_piece_can_be_sold_with_it(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->counterCustomer($owner);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'per_gram')->firstOrFail();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'percentage')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->actingAs($owner)->post(route('items.store'), $this->piece('RING01', $gold, $purity))->assertRedirect();

        $this->actingAs($owner)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('RING01')
            ->assertSee('69,000.00')
            ->assertSee('Find customer')
            ->assertSee('<label for="weigh-wastage">Hallmark</label>', false)
            ->assertSee('data-code="fixed" selected', false)
            ->assertDontSee('Wastage')
            ->assertDontSee('HM (');
        $bangle = Category::query()->where('code', 'BANGLE')->firstOrFail();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'new_piece' => [
                'name' => 'Counter bangle',
                'category_uuid' => $bangle->uuid,
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
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '70980'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $piece = Item::query()->where('name', 'Counter bangle')->firstOrFail();
        $this->assertSame('PC0001', $piece->item_code);
        $this->assertSame(ItemStatus::Sold, $piece->status);
        $this->assertSame($bangle->id, $piece->category_id);
        $this->actingAs($owner)->get(route('sales.show', SaleLine::query()->where('item_id', $piece->id)->firstOrFail()->sale))
            ->assertOk()
            ->assertSee('Hallmark');
        $this->assertSame('70980.00', (string) SaleLine::query()->where('item_id', $piece->id)->firstOrFail()->sale->total);
    }

    public function test_gst_is_on_metal_and_processing_and_hallmark_is_added_after_it(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->counterCustomer($owner);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '15000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));
        $this->seeShop($owner);
        $fixedMaking = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();
        $fixedHallmark = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail();

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('value="separate" selected', false)
            ->assertSee('Processing charge with GST 3%');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '500',
            'new_pieces' => [[
                'name' => 'Ring',
                'category_uuid' => Category::query()->where('code', 'RING')->firstOrFail()->uuid,
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '2',
                'other_weight' => '0',
                'stone_value' => '500',
                'making_method_uuid' => $fixedMaking->uuid,
                'making_value' => '1000',
                'wastage_method_uuid' => $fixedHallmark->uuid,
                'wastage_value' => '45',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '31975']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $sale = Sale::query()->sole();
        $this->assertSame('separate', $sale->making_mode);
        $this->assertSame('31545.00', (string) $sale->lines_amount);
        $this->assertSame('31000.00', (string) $sale->taxable_amount);
        $this->assertSame('930.00', (string) $sale->tax_amount);
        $this->assertSame('45.00', (string) $sale->hallmark_amount);
        $this->assertSame('0.00', (string) $sale->round_off);
        $this->assertSame('31975.00', (string) $sale->total);
        $this->assertSame('0.00', $sale->balanceDue());

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSeeInOrder(['Jewellery value', '30,500.00', 'Processing charge', '1,000.00', 'Discount', '500.00', 'Taxable value', '31,000.00', 'CGST 1.5%', '465.00', 'SGST 1.5%', '465.00', 'Hallmark (no GST)', '45.00', 'Total', '31,975.00'])
            ->assertDontSee('on processing charge');

        $sale->forceFill(['hallmark_amount' => null])->save();
        $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk()->assertDontSee('Hallmark (no GST)');
        $this->actingAs($owner)->get(route('sales.edit', $sale))->assertOk()->assertSee('"hallmarkOutside":false', false);
        $this->actingAs($owner)->put(route('sales.update', $sale), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '500',
            'making_mode' => 'separate',
            'new_pieces' => [[
                'name' => 'Ring',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '2',
                'other_weight' => '0',
                'stone_value' => '500',
                'making_method_uuid' => $fixedMaking->uuid,
                'making_value' => '1000',
                'wastage_method_uuid' => $fixedHallmark->uuid,
                'wastage_value' => '45',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '31975']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);
        $sale->refresh();
        $this->assertNull($sale->hallmark_amount);
        $this->assertSame('31976.00', (string) $sale->total);
    }

    public function test_the_bill_preview_shows_the_invoice_and_saves_nothing(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->counterCustomer($owner, 'Preview Buyer');
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));
        $this->seeShop($owner);
        $bill = [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'new_pieces' => [[
                'name' => 'Preview chain',
                'category_uuid' => Category::query()->where('code', 'CHAIN')->firstOrFail()->uuid,
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '10',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '61800']],
        ];
        $items = Item::query()->count();
        $ledger = \App\Models\LedgerEntry::query()->count();

        $this->actingAs($owner)->get(route('sales.create'))->assertOk()->assertSee('Preview bill');
        $this->actingAs($owner)->postJson(route('sales.preview'), $bill)
            ->assertOk()
            ->assertSee('Preview · not saved yet')
            ->assertSee('Preview Buyer')
            ->assertSee('Preview chain')
            ->assertSee('61,800.00')
            ->assertSee('JJ/26-27/0001')
            ->assertDontSee('Scan to view bill');

        $this->seeShop($owner);
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame($items, Item::query()->count());
        $this->assertSame($ledger, \App\Models\LedgerEntry::query()->count());

        $this->actingAs($owner)->postJson(route('sales.preview'), ['customer_uuid' => $buyer->uuid, 'discount' => '0'] + ['payments' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_ids']);

        $this->actingAs($owner)->post(route('sales.store'), $bill)->assertRedirect();
        $this->seeShop($owner);
        $this->assertSame('JJ/26-27/0001', Sale::query()->sole()->number);
    }

    public function test_a_piece_typed_on_the_bill_keeps_the_huid_typed_with_it(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->counterCustomer($owner);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->actingAs($owner)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('id="weigh-own"', false)
            ->assertDontSee('id="weigh-own" checked', false)
            ->assertSee('id="weigh-huid"', false)
            ->assertDontSee('for="weigh-hsn"', false)
            ->assertSee('bill-products', false)
            ->assertSee('>Ring<', false)
            ->assertSee('Type the hallmark ID if this piece is not in stock');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'new_pieces' => [[
                'name' => 'Loose bangle',
                'huid' => 'ab12cd',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
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
        $piece = Item::query()->where('name', 'Loose bangle')->firstOrFail();
        $this->assertSame('AB12CD', $piece->huid);
        $sale = Sale::query()->latest('id')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Loose bangle')
            ->assertSee('AB12CD')
            ->assertSeeInOrder(['<th>HUID</th>', '<th>HSN</th>'], false)
            ->assertSee('7113');
    }

    public function test_two_rings_keep_their_own_stones_on_the_bill(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->counterCustomer($owner);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $piece = function (string $name, string $gross, array $stones) use ($gold, $purity, $location): array {
            return [
                'name' => $name,
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => $gross,
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
                'stones' => $stones,
            ];
        };

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'new_pieces' => [
                $piece('Ring A', '10', [['name' => 'Diamond', 'weight' => '1', 'value' => '5000']]),
                $piece('Ring B', '8', [['name' => 'Ruby', 'weight' => '0.5', 'value' => '2000']]),
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '109180'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $sale = Sale::query()->latest('id')->firstOrFail();
        $lines = $sale->lines()->with('stones')->get()->keyBy('name');
        $ringA = $lines['Ring A'];
        $ringB = $lines['Ring B'];

        $this->assertCount(2, $lines);
        $this->assertSame('54000.00', (string) $ringA->metal_amount);
        $this->assertSame('5000.00', (string) $ringA->stone_amount);
        $this->assertSame('Diamond', $ringA->stones->sole()->name);
        $this->assertSame('1.000', (string) $ringA->stones->sole()->weight);
        $this->assertSame('45000.00', (string) $ringB->metal_amount);
        $this->assertSame('Ruby', $ringB->stones->sole()->name);
        $this->assertSame('2000.00', (string) $ringB->stones->sole()->value);
        $this->assertNotSame($ringA->id, $ringB->stones->sole()->sale_line_id);

        $this->actingAs($owner)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Ring A')
            ->assertSee('Diamond')
            ->assertSee('Ring B')
            ->assertSee('Ruby')
            ->assertSee('54,000.00')
            ->assertSee('5,000.00')
            ->assertSee('2,000.00');
    }

    public function test_a_stock_piece_keeps_its_diamond_with_the_gold(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $buyer = $this->counterCustomer($owner);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Diamond ring',
            'item_code' => 'RINGD1',
            'sku' => 'RINGD1',
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '10',
            'other_weight' => '0',
            'making_value' => '0',
            'wastage_value' => '0',
            'cost_price' => '0',
            'selling_price' => '0',
            'mrp' => '0',
            'stones' => [
                ['name' => 'Diamond', 'weight' => '1', 'value' => '8000'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'RINGD1')->firstOrFail();
        $this->assertSame('1.000', (string) $item->stone_weight);
        $this->assertSame('9.000', (string) $item->net_weight);
        $this->assertSame('8000.00', (string) $item->stone_value);
        $this->assertSame('Diamond', $item->stones()->sole()->name);

        $this->actingAs($owner)->get(route('items.show', $item))->assertOk()->assertSee('Diamond');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'item_ids' => [$item->uuid],
            'payments' => [
                ['method' => 'cash', 'amount' => '63860'],
            ],
        ])->assertRedirect();

        $this->seeShop($owner);
        $line = SaleLine::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame('54000.00', (string) $line->metal_amount);
        $this->assertSame('8000.00', (string) $line->stone_amount);
        $this->assertSame('Diamond', $line->stones()->sole()->name);
        $this->assertSame($line->id, $line->stones()->sole()->sale_line_id);
    }

    public function test_silver_bill_keeps_chosen_purity_when_only_999_has_a_shop_rate(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $silver = MetalType::query()->where('code', 'SILVER')->firstOrFail();
        $fine = Purity::query()->where('code', '999')->where('metal_type_id', $silver->id)->firstOrFail();
        $sterling = Purity::query()->where('code', '925')->where('metal_type_id', $silver->id)->firstOrFail();
        $buyer = $this->counterCustomer($owner);
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $silver->uuid,
            'purity_uuid' => $fine->uuid,
            'rate_per_gram' => '90',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));

        $this->seeShop($owner);
        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'new_pieces' => [[
                'name' => 'Silver chain',
                'metal_uuid' => $silver->uuid,
                'purity_uuid' => $sterling->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '10',
                'other_weight' => '0',
                'rate_per_gram' => '75',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '750']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->seeShop($owner);
        $line = SaleLine::query()->sole();
        $this->assertSame('925', $line->purity_name);
        $this->assertSame('75.00', (string) $line->rate_per_gram);
        $this->assertSame((int) $sterling->id, (int) $line->item->purity_id);

        $this->actingAs($owner)->get(route('sales.show', $line->sale))
            ->assertOk()
            ->assertSee('Silver 925')
            ->assertDontSee('Silver 999');
    }

    public function test_another_shop_cannot_open_this_piece(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $this->actingAs($owner)->post(route('items.store'), $this->piece('RING01', $gold, $purity))->assertRedirect();
        $this->seeShop($owner);
        $item = Item::query()->where('item_code', 'RING01')->firstOrFail();

        $other = $this->shopUser([
            'name' => 'Other Jewellers',
            'code' => 'OTHER',
            'gstin' => '29ABCDE1234F1Z5',
            'pan' => 'ABCDE1234F',
            'email' => 'other@jagdamba.test',
        ], [
            'email' => 'owner-other@jagdamba.test',
        ]);

        $this->actingAs($other)->get(route('items.show', $item))->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function piece(string $code, MetalType $gold, Purity $purity): array
    {
        $this->seeShop(User::query()->where('company_id', $gold->company_id)->firstOrFail());
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

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

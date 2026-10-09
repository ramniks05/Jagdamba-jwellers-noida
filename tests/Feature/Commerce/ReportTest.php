<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Commerce\LedgerService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_stock_report_pages_filters_and_totals_the_whole_filter(): void
    {
        $owner = $this->shopUser();
        $this->rate($owner);

        foreach (range(1, 27) as $number) {
            $this->addPiece($owner, sprintf('RING%02d', $number));
        }

        $this->actingAs($owner)->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('Showing 1–25 of 27 pieces')
            ->assertSee('RING01')
            ->assertSee('RING25')
            ->assertDontSee('RING26')
            ->assertSee('Total · 27 pieces')
            ->assertSee('270.000')
            ->assertSee('Print report');

        $this->actingAs($owner)->get(route('reports.stock', ['page' => 2]))
            ->assertOk()
            ->assertSee('Showing 26–27 of 27 pieces')
            ->assertSee('RING27')
            ->assertDontSee('RING01');

        $this->actingAs($owner)->get(route('reports.stock', ['per_page' => 50]))
            ->assertOk()
            ->assertSee('RING27')
            ->assertSee('Showing 1–27 of 27 pieces');

        $this->actingAs($owner)->get(route('reports.stock', ['per_page' => 50, 'page' => 9]))
            ->assertOk()
            ->assertSee('Showing 1–27 of 27 pieces');

        $this->actingAs($owner)->get(route('reports.stock', ['all' => 1, 'print' => 1]))
            ->assertOk()
            ->assertSee('RING01')
            ->assertSee('RING27')
            ->assertDontSee('Showing 1–')
            ->assertSee('window.print()', false);

        $this->actingAs($owner)->get(route('reports.stock', ['search' => 'RING27']))
            ->assertOk()
            ->assertSee('Total · 1 piece')
            ->assertSee('RING27')
            ->assertDontSee('RING01');

        $this->actingAs($owner)->get(route('reports.stock', ['search' => '%']))
            ->assertOk()
            ->assertSee('No pieces in stock for this filter.');

        $this->actingAs($owner)->get(route('reports.stock', ['sort' => 'newest']))
            ->assertOk()
            ->assertSee('RING27')
            ->assertDontSee('RING01');

        $this->actingAs($owner)->get(route('reports.stock', ['status' => 'reserved']))
            ->assertOk()
            ->assertSee('No pieces in stock for this filter.');
    }

    public function test_the_sales_report_filters_by_payment_date_and_customer(): void
    {
        $owner = $this->shopUser();
        $this->rate($owner);
        $this->addPiece($owner, 'RING01');
        $this->addPiece($owner, 'RING02');
        $meera = $this->customer($owner, 'Meera Shah', '9876500000');
        $ravi = $this->customer($owner, 'Ravi Verma', '9876500001');

        $this->sell($owner, $meera, 'RING01', '10000');
        $this->sell($owner, $ravi, 'RING02', '70555');

        $this->actingAs($owner)->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('Meera Shah')
            ->assertSee('Ravi Verma')
            ->assertSee('Total · 2 bills')
            ->assertSee('1,41,110.00')
            ->assertSee('60,555.00');

        $this->actingAs($owner)->get(route('reports.sales', ['payment' => 'due']))
            ->assertOk()
            ->assertSee('Meera Shah')
            ->assertDontSee('Ravi Verma')
            ->assertSee('Total · 1 bill');

        $this->actingAs($owner)->get(route('reports.sales', ['payment' => 'paid']))
            ->assertOk()
            ->assertSee('Ravi Verma')
            ->assertDontSee('Meera Shah');

        $this->actingAs($owner)->get(route('reports.sales', ['search' => '9876500001']))
            ->assertOk()
            ->assertSee('Ravi Verma')
            ->assertDontSee('Meera Shah');

        $this->actingAs($owner)->get(route('reports.sales', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('No bills for this filter.');

        $this->actingAs($owner)->get(route('reports.sales', ['from' => '2026-10-31', 'to' => '2026-10-01']))
            ->assertOk()
            ->assertSee('Total · 2 bills');

        $this->actingAs($owner)->get(route('reports.sales', ['from' => 'not-a-date']))
            ->assertOk()
            ->assertSee('Total · 2 bills');
    }

    public function test_the_outstanding_report_lists_dues_and_advances_and_stays_closed_to_a_cashier(): void
    {
        $owner = $this->shopUser();
        $this->rate($owner);
        $this->addPiece($owner, 'RING01');
        $meera = $this->customer($owner, 'Meera Shah', '9876500000');
        $ravi = $this->customer($owner, 'Ravi Verma', '9876500001');
        $this->sell($owner, $meera, 'RING01', '10000');

        $this->seeShop($owner);
        app(LedgerService::class)->post((int) $owner->company_id, PartyType::Customer, (int) $ravi->id, LedgerDirection::Credit, '5000.00', 'Order advance');

        $this->actingAs($owner)->get(route('reports.outstanding'))
            ->assertOk()
            ->assertSee('Meera Shah')
            ->assertDontSee('Ravi Verma')
            ->assertSee('60,555.00')
            ->assertSee('Total · 1 customer');

        $this->actingAs($owner)->get(route('reports.outstanding', ['show' => 'advance']))
            ->assertOk()
            ->assertSee('Ravi Verma')
            ->assertDontSee('Meera Shah')
            ->assertSee('5,000.00');

        $this->actingAs($owner)->get(route('reports.outstanding', ['search' => 'Ravi']))
            ->assertOk()
            ->assertSee('Nobody has an outstanding balance.');

        $cashier = User::factory()->create(['company_id' => $owner->company_id, 'email' => 'cashier@jagdamba.test']);
        $this->seeShop($owner);
        app(AccessProvisioner::class)->grant($cashier, 'cashier');

        foreach (['reports.stock', 'reports.sales', 'reports.outstanding'] as $route) {
            $this->actingAs($cashier)->get(route($route))->assertForbidden();
        }
    }

    private function rate(User $owner): void
    {
        $this->seeShop($owner);
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => MetalType::query()->where('code', 'GOLD')->firstOrFail()->uuid,
            'purity_uuid' => Purity::query()->where('code', '22K')->firstOrFail()->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect(route('rates.index'));
    }

    private function addPiece(User $owner, string $code): void
    {
        $this->seeShop($owner);

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Gold ring',
            'item_code' => $code,
            'sku' => $code,
            'category_uuid' => Category::query()->where('code', 'RING')->firstOrFail()->uuid,
            'metal_uuid' => MetalType::query()->where('code', 'GOLD')->firstOrFail()->uuid,
            'purity_uuid' => Purity::query()->where('code', '22K')->firstOrFail()->uuid,
            'location_uuid' => StockLocation::query()->where('code', 'MAIN')->firstOrFail()->uuid,
            'gross_weight' => '10',
            'stone_weight' => '0',
            'other_weight' => '0',
            'making_method_uuid' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'per_gram')->firstOrFail()->uuid,
            'making_value' => '500',
            'wastage_method_uuid' => ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'percentage')->firstOrFail()->uuid,
            'wastage_value' => '5',
            'stone_value' => '1000',
            'cost_price' => '60000',
            'selling_price' => '70000',
            'mrp' => '75000',
        ])->assertRedirect();
    }

    private function customer(User $owner, string $name, string $mobile): Customer
    {
        $this->actingAs($owner)->post(route('customers.store'), [
            'name' => $name,
            'mobile' => $mobile,
            'kyc_status' => 'pending',
            'customer_type' => 'retail',
            'is_active' => '1',
        ])->assertRedirect();

        $this->seeShop($owner);

        return Customer::query()->where('mobile', $mobile)->firstOrFail();
    }

    private function sell(User $owner, Customer $customer, string $code, string $cash): void
    {
        $this->seeShop($owner);

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer->uuid,
            'item_ids' => [Item::query()->where('item_code', $code)->firstOrFail()->uuid],
            'discount' => '500',
            'making_mode' => 'inside',
            'payments' => [
                ['method' => 'cash', 'amount' => $cash],
            ],
        ])->assertRedirect();
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

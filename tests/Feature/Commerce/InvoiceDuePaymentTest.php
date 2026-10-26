<?php

namespace Tests\Feature\Commerce;

use App\Enums\PartyType;
use App\Models\MetalType;
use App\Models\Payment;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Commerce\LedgerService;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceDuePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_due_can_be_paid_against_the_invoice(): void
    {
        [$owner, $sale] = $this->partPaidSale();
        $due = BigDecimal::of($sale->balanceDue());
        $part = (string) $due->minus('1000')->toScale(2);

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Balance due on '.$sale->number)
            ->assertSee('Receive payment');

        $this->actingAs($owner)->post(route('sales.payments.store', $sale), [
            'method' => 'upi',
            'amount' => $part,
            'reference' => 'UPI123',
        ])->assertRedirect(route('sales.show', $sale))->assertSessionHasNoErrors();

        app(CompanyContext::class)->set($owner->company);
        $sale->refresh();
        $this->assertSame('1000.00', $sale->balanceDue());
        $payment = Payment::query()->where('sale_id', $sale->id)->where('reference', 'UPI123')->firstOrFail();
        $this->assertSame($part, (string) $payment->amount);
        $this->assertSame('Received against '.$sale->number, $payment->narration);
        $this->assertSame('1000.00', (string) BigDecimal::of(app(LedgerService::class)->balance(PartyType::Customer, (int) $sale->customer_id))->toScale(2));

        $this->actingAs($owner)->post(route('sales.payments.store', $sale), [
            'method' => 'cash',
            'amount' => '1000.01',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($owner)->post(route('sales.payments.store', $sale), [
            'method' => 'cash',
            'amount' => '1000',
        ])->assertSessionHasNoErrors();

        app(CompanyContext::class)->set($owner->company);
        $this->assertSame('0.00', $sale->refresh()->balanceDue());
        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertDontSee('Balance due on '.$sale->number);
        $this->actingAs($owner)->get(route('sales.index', ['status' => 'due']))
            ->assertOk()
            ->assertDontSee($sale->number);
    }

    public function test_the_printed_bill_carries_a_qr_that_opens_the_bill_without_login(): void
    {
        [$owner, $sale] = $this->partPaidSale();
        $owner->company->forceFill([
            'website' => 'https://jagdamba.example',
            'facebook' => 'https://facebook.com/jagdamba',
            'instagram' => 'https://instagram.com/jagdamba',
        ])->save();
        $link = URL::signedRoute('bills.show', ['sale' => $sale->uuid]);

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('data:image/', false)
            ->assertSee('Scan to view bill')
            ->assertSee('Send on WhatsApp');

        Auth::logout();
        app(CompanyContext::class)->forget();

        $this->get($link)
            ->assertOk()
            ->assertSee($sale->number)
            ->assertSee('Download PDF')
            ->assertSee('MEERA SHAH')
            ->assertSee('jagdamba.example', false)
            ->assertSee('class="invoice-links no-print"', false)
            ->assertSee('https://facebook.com/jagdamba', false)
            ->assertSee('https://instagram.com/jagdamba', false)
            ->assertDontSee('Receive payment');

        $this->get(route('bills.show', ['sale' => $sale->uuid]))->assertForbidden();
        $this->get($link.'x')->assertForbidden();
        $this->get(URL::signedRoute('bills.show', ['sale' => (string) Str::uuid()]))->assertNotFound();
    }

    public function test_choosing_a_customer_on_a_bill_shows_the_old_due(): void
    {
        [$owner, $sale] = $this->partPaidSale();
        app(CompanyContext::class)->set($owner->company);
        $due = app(LedgerService::class)->outstandingDues([(int) $sale->customer_id])[(int) $sale->customer_id];
        $amount = trim(preg_replace('/[^\d.,]/', '', app(NumberFormatService::class)->money($due, $owner->company)) ?? '');

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('"due":"Old due', false)
            ->assertSee($amount, false)
            ->assertSee('Meera Shah', false);

        $this->actingAs($owner)->post(route('customers.payments.store', $sale->customer), [
            'method' => 'upi',
            'amount' => $due,
            'reference' => 'SETTLE',
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertDontSee('"due":"Old due', false);
    }

    public function test_a_new_bill_takes_the_old_due_and_settles_the_earlier_bills(): void
    {
        [$owner, $old] = $this->partPaidSale();
        app(CompanyContext::class)->set($owner->company);
        $oldDue = $old->balanceDue();
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $pay = BigDecimal::of((string) $old->total)->plus($oldDue)->minus('3000');

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $old->customer->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'include_previous' => '1',
            'new_pieces' => [[
                'name' => 'Chain',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '10',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
            ]],
            'payments' => [['method' => 'cash', 'amount' => (string) $pay]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(CompanyContext::class)->set($owner->company);
        $sale = Sale::query()->latest('id')->firstOrFail();

        $this->assertSame('0.00', $old->refresh()->balanceDue());
        $this->assertSame($oldDue, (string) $sale->previous_due);
        $this->assertSame(1, (int) $sale->previous_bills);
        $this->assertSame('3000.00', $sale->balanceDue());
        $this->assertSame($sale->balanceDue(), app(LedgerService::class)->balance(PartyType::Customer, (int) $sale->customer_id));

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Old due (1 bill)')
            ->assertSee('Amount payable');
    }

    public function test_paying_more_than_the_bill_keeps_the_extra_as_advance_for_the_next_bill(): void
    {
        [$owner, $sale] = $this->partPaidSale('70000');
        app(CompanyContext::class)->set($owner->company);
        $ledger = app(LedgerService::class);

        $this->assertSame('61800.00', (string) $sale->total);
        $this->assertSame('61800.00', (string) $sale->paid_amount);
        $this->assertSame('0.00', $sale->balanceDue());
        $this->assertSame('8200.00', $sale->extraPaid());
        $this->assertSame('-8200.00', $ledger->balance(PartyType::Customer, (int) $sale->customer_id));
        $this->assertSame('8200.00', $ledger->spendableCredit((int) $sale->customer_id));
        $this->assertStringContainsString('8200.00 kept as advance', (string) $sale->payments()->firstOrFail()->narration);

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Extra kept as advance')
            ->assertSee('8,200.00')
            ->assertSee('70,000.00');
        $this->actingAs($owner)->get(route('sales.create'))
            ->assertOk()
            ->assertSee('"advance":"Advance', false)
            ->assertDontSee('"due":"Old due', false);

        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $sale->customer->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'use_credit' => '8200',
            'new_pieces' => [[
                'name' => 'Stud',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '1',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(CompanyContext::class)->set($owner->company);
        $next = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('6180.00', (string) $next->credit_amount);
        $this->assertSame('0.00', $next->balanceDue());
        $this->assertSame('2020.00', $ledger->spendableCredit((int) $sale->customer_id));
    }

    /**
     * @return array{0: User, 1: Sale}
     */
    private function partPaidSale(string $paid = '50000'): array
    {
        $owner = $this->shopUser();
        app(CompanyContext::class)->set($owner->company);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

        $customer = $this->actingAs($owner)->postJson(route('customers.store'), [
            'name' => 'Meera Shah',
            'mobile' => '9876500000',
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => true,
        ])->assertOk()->json();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $customer['uuid'],
            'discount' => '0',
            'making_mode' => 'inside',
            'new_pieces' => [[
                'name' => 'Ring',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '10',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
            ]],
            'payments' => [['method' => 'cash', 'amount' => $paid]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(CompanyContext::class)->set($owner->company);

        return [$owner, Sale::query()->latest('id')->firstOrFail()];
    }
}

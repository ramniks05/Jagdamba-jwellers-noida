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
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /**
     * @return array{0: User, 1: Sale}
     */
    private function partPaidSale(): array
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
            'payments' => [['method' => 'cash', 'amount' => '50000']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(CompanyContext::class)->set($owner->company);

        return [$owner, Sale::query()->latest('id')->firstOrFail()];
    }
}

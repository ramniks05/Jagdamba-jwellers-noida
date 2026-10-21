<?php

namespace Tests\Feature\Commerce;

use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockLocation;
use App\Services\Commerce\MetalStockService;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_can_be_changed_and_keeps_its_number(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $branch = StockLocation::query()->where('code', 'MAIN')->firstOrFail()->branch;
        $buyer = $this->counterCustomer($owner);

        $this->actingAs($owner)->post(route('reports.stock.store'), [
            'kind' => 'opening',
            'branch_uuid' => $branch->uuid,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'grams' => '100',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('rates.store'), [
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'rate_per_gram' => '6000',
            'source' => 'manual',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'new_pieces' => [[
                'name' => 'Loose bangle',
                'huid' => 'ab12cd',
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
        $sale = Sale::query()->latest('id')->firstOrFail();
        $number = $sale->number;
        $this->assertSame('90.000', $this->balance());

        $this->actingAs($owner)->get(route('sales.edit', $sale))
            ->assertOk()
            ->assertSee('Edit '.$number)
            ->assertSee('Loose bangle')
            ->assertSee('Update '.$number);

        $this->actingAs($owner)->put(route('sales.update', $sale), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'new_pieces' => [[
                'name' => 'Loose bangle',
                'huid' => 'ab12cd',
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => StockLocation::query()->where('code', 'MAIN')->firstOrFail()->uuid,
                'gross_weight' => '8',
                'other_weight' => '0',
                'making_value' => '0',
                'wastage_value' => '0',
            ]],
            'payments' => [
                ['method' => 'cash', 'amount' => '49440'],
            ],
        ])->assertRedirect(route('sales.show', $sale));

        $this->seeShop($owner);
        $sale->refresh();
        $this->assertSame($number, $sale->number);
        $this->assertSame('49440.00', (string) $sale->total);
        $this->assertSame('0.00', $sale->balanceDue());
        $line = SaleLine::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('8.000', (string) $line->net_weight);
        $this->assertSame('AB12CD', $line->item?->huid);
        $this->assertSame('92.000', $this->balance());
        $this->assertSame('0.00', $this->ledger((int) $buyer->id));

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee($number)
            ->assertSee('Edit invoice');
    }

    public function test_a_returned_invoice_cannot_be_edited(): void
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
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('sales.store'), [
            'customer_uuid' => $buyer->uuid,
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
        $sale = Sale::query()->latest('id')->firstOrFail();
        $line = SaleLine::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->actingAs($owner)->post(route('sales.returns.store', $sale), [
            'lines' => [$line->uuid],
            'refund' => '61800',
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('sales.edit', $sale))
            ->assertRedirect(route('sales.show', $sale))
            ->assertSessionHasErrors('sale');
    }

    private function balance(): string
    {
        $row = app(MetalStockService::class)->balances()->firstWhere('purity', '22K');

        return $row['balance'];
    }

    private function ledger(int $customerId): string
    {
        $rows = LedgerEntry::query()->where('party_type', PartyType::Customer)->where('party_id', $customerId)->get();
        $balance = BigDecimal::zero();

        foreach ($rows as $row) {
            $amount = BigDecimal::of((string) $row->amount);
            $balance = $row->direction === LedgerDirection::Debit ? $balance->plus($amount) : $balance->minus($amount);
        }

        return (string) $balance->toScale(2);
    }

    private function seeShop($user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

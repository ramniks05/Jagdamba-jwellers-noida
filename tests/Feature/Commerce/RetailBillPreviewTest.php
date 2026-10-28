<?php

namespace Tests\Feature\Commerce;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailBillPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_includes_retail_line_on_invoice_html(): void
    {
        $owner = $this->shopUser();
        $buyer = $this->counterCustomer($owner);

        $response = $this->actingAs($owner)->post(route('sales.preview'), [
            'customer_uuid' => $buyer->uuid,
            'discount' => '0',
            'making_mode' => 'inside',
            'sold_at' => now(config('app.timezone'))->format('Y-m-d\TH:i'),
            'retail_items' => [[
                'name' => 'Ganesh murti',
                'quantity' => '1',
                'unit_price' => '500',
                'tax_mode' => 'none',
            ]],
            'payments' => [
                ['method' => 'cash', 'amount' => '500'],
            ],
        ]);

        $response->assertOk();
        $response->assertSee('Ganesh Murti', false);
        $response->assertSee('Other item', false);
        $response->assertSee('500.00', false);
    }
}

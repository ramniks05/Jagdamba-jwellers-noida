<?php

namespace Tests\Feature\Commerce;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailBillCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_bill_page_includes_retail_panel_and_billing_row(): void
    {
        $owner = $this->shopUser();
        $html = $this->actingAs($owner)->get(route('sales.create'))->assertOk()->getContent();

        $this->assertStringContainsString('id="retail-add"', $html);
        $this->assertStringContainsString('id="bill-retail-row"', $html);
        $this->assertStringContainsString('function bindRetailPanel()', $html);

        preg_match('/id="bill-config">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $bill = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($bill['retailEnabled'] ?? false);
    }
}

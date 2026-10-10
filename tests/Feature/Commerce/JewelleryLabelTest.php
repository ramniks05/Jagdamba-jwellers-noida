<?php

namespace Tests\Feature\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Models\ChargeMethod;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\AccessProvisioner;
use App\Services\Commerce\JewelleryLabelZplService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JewelleryLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_item_tag_follows_the_reference_layout(): void
    {
        [$owner, $item] = $this->counter('GSE2741');

        $response = $this->actingAs($owner)->get(route('items.label.zpl', $item))->assertOk();
        $zpl = $response->getContent();

        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("^XA\n", $zpl);
        $this->assertStringEndsWith("^XZ\n", $zpl);
        $this->assertStringContainsString("^PW448\n", $zpl);
        $this->assertStringContainsString("^LL104\n", $zpl);
        $this->assertStringContainsString('^CI28', $zpl);
        $this->assertStringContainsString('^FO377,20^BQN,2,3^FDMA,GSE2741^FS', $zpl);
        $this->assertStringContainsString('^FO8,10^A0N,22,19^FH^FDGSE2741  22K^FS', $zpl);
        $this->assertMatchesRegularExpression('/\^FO8,40\^A0N,21,\d+\^FH\^FDG. Wt : 4.610 gm\^FS/', $zpl);
        $this->assertMatchesRegularExpression('/\^FO8,68\^A0N,21,\d+\^FH\^FDN. Wt. : 4.610 gm\^FS/', $zpl);
        $this->assertMatchesRegularExpression('/\^FO292,12\^A0N,20,\d+\^FB81,1,0,C\^FH\^FDring\^FS/', $zpl);
        $this->assertMatchesRegularExpression('/\^FO292,44\^A0N,23,\d+\^FB81,1,0,C\^FH\^FDJagdamba\^FS/', $zpl);
        $this->assertMatchesRegularExpression('/\^FO292,71\^A0N,23,\d+\^FB81,1,0,C\^FH\^FDJewellers\^FS/', $zpl);
        $this->assertGreaterThanOrEqual(292 + 81, 377, 'The QR code starts after the shop name column.');
        $this->assertLessThanOrEqual(JewelleryLabelZplService::RIGHT_PANEL[1] - JewelleryLabelZplService::MARGIN, 377 + 63 - 1, 'The QR code stays inside the second flap.');
        $this->assertStringContainsString('^PQ1,0,1,Y', $zpl);
        $this->assertSame(1, substr_count($zpl, '^XA'));

        foreach (['~SD', '^MN', '^PR', '^MD', '^JU'] as $printerSetting) {
            $this->assertStringNotContainsString($printerSetting, $zpl);
        }
    }

    public function test_everything_stays_inside_its_panel_and_the_fold_stays_blank(): void
    {
        [$owner, $item] = $this->counter('GSE2741');
        $item->forceFill(['gross_weight' => '123.456', 'net_weight' => '120.001', 'barcode' => 'JAGDAMBA-OLD-TAG-0001'])->save();

        foreach ([$item->fresh(), null] as $subject) {
            $layout = app(JewelleryLabelZplService::class)->preview($subject);
            [$foldStart, $foldEnd] = JewelleryLabelZplService::FOLD;
            $boxes = [['x' => $layout['code']['x'], 'y' => $layout['code']['y'], 'w' => $layout['code']['size'], 'h' => $layout['code']['size']]];

            foreach ($layout['texts'] as $text) {
                $boxes[] = ['x' => $text['x'], 'y' => $text['y'], 'w' => $text['width'], 'h' => $text['height']];
            }

            foreach ($boxes as $box) {
                $right = $box['x'] + $box['w'] - 1;
                $panel = $box['x'] < $foldStart ? JewelleryLabelZplService::LEFT_PANEL : JewelleryLabelZplService::RIGHT_PANEL;

                $leftFlap = $box['x'] < $foldStart;
                $this->assertGreaterThanOrEqual($panel[0] + ($leftFlap ? JewelleryLabelZplService::MARGIN : JewelleryLabelZplService::FOLD_SIDE), $box['x']);
                $this->assertLessThanOrEqual($panel[1] - ($leftFlap ? JewelleryLabelZplService::FOLD_SIDE : JewelleryLabelZplService::MARGIN), $right);
                $this->assertLessThanOrEqual(160, $box['w'], 'Each flap is only 20 mm wide.');
                $this->assertGreaterThanOrEqual(JewelleryLabelZplService::MARGIN, $box['y']);
                $this->assertLessThanOrEqual(JewelleryLabelZplService::HEIGHT - JewelleryLabelZplService::MARGIN, $box['y'] + $box['h']);
                $this->assertTrue($right < $foldStart || $box['x'] > $foldEnd, 'Something is printed on the fold.');
            }
        }

        $zpl = $this->actingAs($owner)->get(route('items.label.zpl', $item))->assertOk()->getContent();
        $this->assertStringContainsString('^FDG. Wt : 123.456 gm^FS', $zpl);
        $this->assertStringContainsString('^FDN. Wt. : 120.001 gm^FS', $zpl);
        $this->assertStringContainsString('^FDMA,JAGDAMBA-OLD-TAG-0001^FS', $zpl);
    }

    public function test_the_code_keeps_an_existing_barcode_unless_set_to_piece_code(): void
    {
        [$owner, $item] = $this->counter('GSE2742');
        $item->forceFill(['barcode' => 'OLD-TAG/889'])->save();

        $zpl = $this->actingAs($owner)->get(route('items.label.zpl', $item))->assertOk()->getContent();
        $this->assertStringContainsString('^FDMA,OLD-TAG/889^FS', $zpl);

        app(SettingService::class)->setMany($owner->company, ['label.barcode_payload' => 'item_code']);

        $zpl = $this->actingAs($owner)->get(route('items.label.zpl', $item))->assertOk()->getContent();
        $this->assertStringContainsString('^FDMA,GSE2742^FS', $zpl);
    }

    public function test_text_is_hex_escaped_so_it_cannot_inject_printer_commands(): void
    {
        [$owner, $item] = $this->counter('GSE2743');
        $item->forceFill(['name' => 'Haar~JA^XZ'])->save();

        $zpl = $this->actingAs($owner)->get(route('items.label.zpl', $item))->assertOk()->getContent();

        $this->assertStringContainsString('^FH^FDhaar_7Eja_5Exz^FS', $zpl);
        $this->assertSame(1, substr_count($zpl, '^XZ'));
        $this->assertStringNotContainsString('~ja', $zpl);
        $this->assertSame('Ring _5EXZ_7EJA_5Fx _E0_A4_B9_E0_A4_BE_E0_A4_B0', JewelleryLabelZplService::escape("Ring ^XZ~JA_x\nहार"));
    }

    public function test_a_piece_code_too_long_for_the_left_panel_is_refused_not_cut(): void
    {
        [$owner, $item] = $this->counter('GSE2740');
        $item->forceFill(['item_code' => 'VERYLONGPIECECODE-2026-0001'])->save();

        $this->actingAs($owner)->getJson(route('items.label.zpl', $item))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payload');
    }

    public function test_a_code_with_printer_control_characters_is_refused(): void
    {
        [$owner, $item] = $this->counter('GSE2744');
        $item->forceFill(['barcode' => 'AB^XZ'])->save();

        $this->actingAs($owner)->getJson(route('items.label.zpl', $item))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payload');
    }

    public function test_copies_are_checked_against_the_limit(): void
    {
        [$owner, $item] = $this->counter('GSE2745');

        $zpl = $this->actingAs($owner)->get(route('items.label.zpl', [$item, 'copies' => 3]))->assertOk()->getContent();
        $this->assertStringContainsString('^PQ3,0,1,Y', $zpl);

        foreach (['0', '21', 'abc', '2.5', '-1'] as $copies) {
            $this->actingAs($owner)->getJson(route('items.label.zpl', [$item, 'copies' => $copies]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('copies');
        }
    }

    public function test_only_signed_in_staff_with_print_rights_in_the_same_shop_get_tags(): void
    {
        [$owner, $item] = $this->counter('GSE2746');
        $this->app['auth']->forgetGuards();

        $this->get(route('items.label.zpl', $item))->assertRedirect(route('login'));
        $this->getJson(route('items.label.zpl', $item))->assertUnauthorized();

        $cashier = User::factory()->create(['company_id' => $owner->company_id, 'email' => 'cashier@jagdamba.test']);
        app(AccessProvisioner::class)->grant($cashier, 'cashier');
        $this->actingAs($cashier)->get(route('items.label.zpl', $item))->assertForbidden();
        $this->actingAs($cashier)->get(route('items.label', $item))->assertForbidden();
        $this->actingAs($cashier)->get(route('labels.test.zpl'))->assertForbidden();

        $other = $this->shopUser(['name' => 'Other Shop', 'legal_name' => 'Other Shop', 'code' => 'OTHER', 'email' => 'other@shop.test', 'gstin' => null, 'pan' => null]);
        $this->actingAs($other)->get(route('items.label.zpl', $item))->assertNotFound();
        $this->actingAs($other)->get(route('items.label', $item))->assertNotFound();
    }

    public function test_the_test_tag_needs_no_piece_and_uses_the_shop_name(): void
    {
        $owner = $this->shopUser(['name' => 'Shree Ganesh Gold']);
        $this->seeShop($owner);

        $zpl = $this->actingAs($owner)->get(route('labels.test.zpl'))->assertOk()->getContent();

        $this->assertStringContainsString('^FDMA,TEST-0001^FS', $zpl);
        $this->assertStringContainsString('^FDGanesh Gold^FS', $zpl);
        $this->assertStringNotContainsString('Jagdamba', $zpl);
        $this->actingAs($owner)->get(route('labels.test'))->assertOk()->assertSee('Print test tag')->assertSee('TEST-0001');
    }

    public function test_the_preview_page_shows_the_tag_and_printer(): void
    {
        [$owner, $item] = $this->counter('GSE2747');

        $this->actingAs($owner)->get(route('items.label', $item))
            ->assertOk()
            ->assertSee('Print tag for GSE2747')
            ->assertSee('ZDesigner ZD230-203dpi ZPL')
            ->assertSee('vendor/qz-tray/qz-tray.js', false)
            ->assertSee('js/jewellery-label-printer.js', false)
            ->assertSee('G. Wt : 4.610 gm');

        $this->actingAs($owner)->get(route('items.show', $item))->assertSee(route('items.label', $item), false);
    }

    public function test_batch_tags_report_pieces_that_could_not_be_made(): void
    {
        [$owner, $first] = $this->counter('GSE2748');
        $second = $this->piece($owner, 'GSE2749');
        $broken = $this->piece($owner, 'GSE2750');
        $broken->forceFill(['barcode' => 'BAD~CODE'])->save();
        $missing = (string) Str::uuid();

        $response = $this->actingAs($owner)->postJson(route('labels.batch'), [
            'items' => [$first->uuid, $second->uuid, $broken->uuid, $missing],
            'copies' => 2,
        ])->assertOk();

        $this->assertSame(['GSE2748', 'GSE2749'], array_column($response->json('tags'), 'code'));
        $this->assertStringContainsString('^PQ2,0,1,Y', $response->json('tags.0.zpl'));
        $this->assertSame(['GSE2750', null], array_column($response->json('errors'), 'code'));

        $this->actingAs($owner)->postJson(route('labels.batch'), [
            'items' => array_map(fn () => (string) Str::uuid(), range(1, 101)),
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_printing_tags_does_not_touch_stock(): void
    {
        [$owner, $item] = $this->counter('GSE2751');
        $before = $item->fresh()->toArray();
        $movements = InventoryTransaction::query()->count();

        $this->actingAs($owner)->get(route('items.label.zpl', [$item, 'copies' => 5]))->assertOk();
        $this->actingAs($owner)->postJson(route('labels.batch'), ['items' => [$item->uuid]])->assertOk();

        $this->assertSame($before, $item->fresh()->toArray());
        $this->assertSame($movements, InventoryTransaction::query()->count());
    }

    public function test_tag_settings_hold_only_printer_choices_and_are_saved_by_managers(): void
    {
        [$owner, $item] = $this->counter('GSE2752');

        $this->actingAs($owner)->get(route('labels.settings.edit'))
            ->assertOk()
            ->assertSee('Barcode tag settings')
            ->assertDontSee('Tag width')
            ->assertDontSee('Code dot size');
        $this->assertSame(['printer_name', 'barcode_payload', 'max_copies'], array_keys(app(JewelleryLabelZplService::class)->saved($owner->company)));

        $this->actingAs($owner)->post(route('labels.settings.update'), ['settings' => $this->labelPayload($owner, ['label.printer_name' => 'ZDesigner ZD230-203dpi ZPL (Copy 1)', 'label.max_copies' => '5'])])
            ->assertRedirect(route('labels.settings.edit'))
            ->assertSessionHasNoErrors();
        $this->seeShop($owner);

        $this->actingAs($owner)->getJson(route('items.label.zpl', [$item, 'copies' => 6]))->assertStatus(422);
        $this->actingAs($owner)->get(route('items.label', $item))->assertSee('ZDesigner ZD230-203dpi ZPL (Copy 1)');

        $this->actingAs($owner)->post(route('labels.settings.update'), ['settings' => $this->labelPayload($owner, ['label.max_copies' => '0'])])
            ->assertSessionHasErrors();
        $this->actingAs($owner)->post(route('labels.settings.update'), ['settings' => $this->labelPayload($owner, ['label.printer_name' => "Zebra\n^XA"])])
            ->assertSessionHasErrors();

        $stock = User::factory()->create(['company_id' => $owner->company_id, 'email' => 'stock@jagdamba.test']);
        app(AccessProvisioner::class)->grant($stock, 'inventory_manager');
        $this->actingAs($stock)->get(route('labels.settings.edit'))->assertOk();
        $this->actingAs($stock)->get(route('items.label.zpl', $item))->assertOk();
        $this->actingAs($stock)->post(route('labels.settings.update'), ['settings' => $this->labelPayload($owner, [])])->assertForbidden();
    }

    public function test_the_main_settings_page_saves_without_tag_settings(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        $payload = [];

        foreach (app(SettingService::class)->formFields($owner->company) as $field) {
            if (! str_starts_with($field['key'], 'label.')) {
                $payload[] = ['key' => $field['key'], 'value' => is_bool($field['value']) ? ($field['value'] ? '1' : '0') : $field['value']];
            }
        }

        $this->actingAs($owner)->put(route('settings.update'), ['settings' => $payload])
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHasNoErrors();
    }

    public function test_qz_signing_stays_off_until_the_server_has_a_key(): void
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);
        config(['services.qz.certificate' => null, 'services.qz.private_key' => null]);

        $this->actingAs($owner)->get(route('labels.qz.certificate'))->assertNoContent();
        $this->actingAs($owner)->postJson(route('labels.qz.sign'), ['request' => 'abc'])->assertNotFound();
        $this->actingAs($owner)->post(route('labels.qz.sign'), [])->assertSessionHasErrors('request');
    }

    public function test_qz_requests_are_signed_with_the_server_key(): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $bundled = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        if (is_file($bundled)) {
            $options['config'] = $bundled;
        }

        $key = @openssl_pkey_new($options);

        if ($key === false) {
            $this->markTestSkipped('OpenSSL cannot make keys on this machine.');
        }

        openssl_pkey_export($key, $pem, null, $options);
        $public = openssl_pkey_get_details($key)['key'];
        $path = tempnam(sys_get_temp_dir(), 'qz');
        file_put_contents($path, $pem);

        try {
            $owner = $this->shopUser();
            $this->seeShop($owner);
            config(['services.qz.private_key' => $path]);

            $signature = $this->actingAs($owner)->post(route('labels.qz.sign'), ['request' => 'print-request-123'])->assertOk()->getContent();

            $this->assertSame(1, openssl_verify('print-request-123', base64_decode($signature), $public, OPENSSL_ALGO_SHA512));
            $this->assertStringNotContainsString('PRIVATE KEY', $this->actingAs($owner)->get(route('items.index'))->getContent());
        } finally {
            @unlink($path);
        }
    }

    public function test_the_shop_name_is_split_over_two_balanced_lines(): void
    {
        $this->assertSame(['Jagdamba', 'Jewellers'], JewelleryLabelZplService::splitInTwo('Jagdamba Jewellers'));
        $this->assertSame(['Shree', 'Ganesh Gold'], JewelleryLabelZplService::splitInTwo('Shree Ganesh Gold'));
        $this->assertSame(['Tanishq'], JewelleryLabelZplService::splitInTwo('Tanishq'));
    }

    public function test_millimetres_become_printer_dots(): void
    {
        $this->assertSame(8, JewelleryLabelZplService::mmToDots(1));
        $this->assertSame(JewelleryLabelZplService::WIDTH, JewelleryLabelZplService::mmToDots(56));
        $this->assertSame(JewelleryLabelZplService::HEIGHT, JewelleryLabelZplService::mmToDots(13));
        $this->assertSame(JewelleryLabelZplService::FOLD[0], JewelleryLabelZplService::mmToDots(20));
        $this->assertSame(JewelleryLabelZplService::FOLD[1] + 1, JewelleryLabelZplService::mmToDots(36));
        $this->assertSame(JewelleryLabelZplService::RIGHT_PANEL[0], JewelleryLabelZplService::mmToDots(36));
        $this->assertSame(21, JewelleryLabelZplService::qrModules('GSE2741'));
        $this->assertSame(25, JewelleryLabelZplService::qrModules('JAGDAMBA-OLD-TAG-0001'));
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return list<array{key: string, value: mixed}>
     */
    private function labelPayload(User $owner, array $changes): array
    {
        $rows = [];

        foreach (app(SettingService::class)->formFields($owner->company) as $index => $field) {
            if (str_starts_with($field['key'], 'label.')) {
                $value = $changes[$field['key']] ?? $field['value'];
                $rows[$index] = ['key' => $field['key'], 'value' => is_bool($value) ? ($value ? '1' : '0') : $value];
            }
        }

        return $rows;
    }

    /**
     * @return array{0: User, 1: Item}
     */
    private function counter(string $code): array
    {
        $owner = $this->shopUser();
        $this->seeShop($owner);

        return [$owner, $this->piece($owner, $code)];
    }

    private function piece(User $owner, string $code): Item
    {
        $gold = MetalType::query()->where('code', 'GOLD')->firstOrFail();
        $purity = Purity::query()->where('code', '22K')->firstOrFail();
        $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('code', 'fixed')->firstOrFail();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('code', 'fixed')->firstOrFail();

        $this->actingAs($owner)->post(route('items.store'), [
            'name' => 'Ring',
            'item_code' => $code,
            'sku' => $code,
            'metal_uuid' => $gold->uuid,
            'purity_uuid' => $purity->uuid,
            'location_uuid' => $location->uuid,
            'gross_weight' => '4.61',
            'stone_weight' => '0',
            'other_weight' => '0',
            'making_method_uuid' => $making->uuid,
            'making_value' => '500',
            'wastage_method_uuid' => $wastage->uuid,
            'wastage_value' => '0',
            'stone_value' => '0',
            'cost_price' => '0',
            'selling_price' => '0',
            'mrp' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->seeShop($owner);

        return Item::query()->where('item_code', $code)->firstOrFail();
    }

    private function seeShop(User $user): void
    {
        app(CompanyContext::class)->set($user->company);
    }
}

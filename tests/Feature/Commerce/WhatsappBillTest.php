<?php

namespace Tests\Feature\Commerce;

use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsappBillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.app_secret' => 'app-secret',
            'services.whatsapp.verify_token' => 'verify-me',
            'services.whatsapp.bill_template' => 'bill_ready',
            'services.whatsapp.bill_template_language' => 'en',
            'services.whatsapp.bill_template_button' => true,
        ]);
    }

    public function test_the_bill_is_sent_through_the_cloud_api_with_the_approved_template(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST1']]])]);
        [$owner, $sale] = $this->sale('9876500000');

        $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk()->assertSee('Send on WhatsApp');
        $this->actingAs($owner)->post(route('sales.whatsapp.store', $sale))
            ->assertRedirect(route('sales.show', $sale))
            ->assertSessionHas('status', 'Bill '.$sale->number.' sent on WhatsApp to +919876500000.');

        Http::assertSent(function (Request $request) use ($sale): bool {
            $body = $request->data();
            $button = $body['template']['components'][1]['parameters'][0]['text'];

            return $request->url() === 'https://graph.facebook.com/v26.0/1234567890/messages'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $body['to'] === '919876500000'
                && $body['template']['name'] === 'bill_ready'
                && $body['template']['components'][0]['parameters'][0]['text'] === 'Meera Shah'
                && $body['template']['components'][0]['parameters'][1]['text'] === $sale->number
                && str_starts_with($button, $sale->uuid.'?signature=');
        });

        app(CompanyContext::class)->set($owner->company);
        $message = WhatsappMessage::query()->sole();
        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.TEST1', $message->provider_id);
        $this->assertSame($sale->id, $message->sale_id);

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Send again on WhatsApp')
            ->assertSee('+919876500000');
    }

    public function test_without_a_button_the_link_goes_in_the_message_body(): void
    {
        config(['services.whatsapp.bill_template_button' => false]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST2']]])]);
        [$owner, $sale] = $this->sale('9876500000');

        $this->actingAs($owner)->post(route('sales.whatsapp.store', $sale))->assertSessionHasNoErrors();

        Http::assertSent(function (Request $request) use ($sale): bool {
            $components = $request->data()['template']['components'];

            return count($components) === 1 && str_contains($components[0]['parameters'][3]['text'], '/bill/'.$sale->uuid.'?signature=');
        });
    }

    public function test_a_rejected_send_is_logged_and_shown_to_staff(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid parameter', 'code' => 131030, 'error_data' => ['details' => 'Recipient phone number not in allowed list']],
        ], 400)]);
        [$owner, $sale] = $this->sale('9876500000');

        $this->actingAs($owner)->post(route('sales.whatsapp.store', $sale))
            ->assertSessionHasErrors(['whatsapp' => 'WhatsApp did not send the bill: (131030) Recipient phone number not in allowed list']);

        app(CompanyContext::class)->set($owner->company);
        $this->assertSame('failed', WhatsappMessage::query()->sole()->status);
        $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk()->assertSee('Recipient phone number not in allowed list');
    }

    public function test_it_refuses_when_not_set_up_or_the_mobile_is_unusable(): void
    {
        Http::fake();
        [$owner, $sale] = $this->sale('9876500000');
        $sale->customer->forceFill(['mobile' => '12345'])->saveQuietly();

        $this->actingAs($owner)->post(route('sales.whatsapp.store', $sale))
            ->assertSessionHasErrors(['whatsapp' => 'This customer has no valid mobile number for WhatsApp.']);

        config(['services.whatsapp.token' => null]);
        $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk()->assertSee('https://wa.me/', false);
        $this->actingAs($owner)->post(route('sales.whatsapp.store', $sale))
            ->assertSessionHasErrors(['whatsapp' => 'WhatsApp is not set up yet. Add the WhatsApp details to the .env file.']);

        Http::assertNothingSent();
    }

    public function test_the_webhook_verifies_and_moves_delivery_status_forward(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST3']]])]);
        [$owner, $sale] = $this->sale('9876500000');
        $this->actingAs($owner)->post(route('sales.whatsapp.store', $sale));

        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')
            ->assertOk()
            ->assertSeeText('42');
        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();

        $this->webhook('read', 'app-secret')->assertOk();
        $this->webhook('delivered', 'app-secret')->assertOk();
        $this->webhook('failed', 'wrong-secret')->assertForbidden();

        app(CompanyContext::class)->set($owner->company);
        $this->assertSame('read', WhatsappMessage::query()->sole()->status);
    }

    private function webhook(string $status, string $secret): TestResponse
    {
        $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
            'statuses' => [['id' => 'wamid.TEST3', 'status' => $status, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '919876500000']],
        ]]]]]], JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, $secret),
        ], $payload);
    }

    /**
     * @return array{0: User, 1: Sale}
     */
    private function sale(string $mobile): array
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
            'mobile' => $mobile,
            'customer_type' => 'retail',
            'kyc_status' => 'pending',
            'is_active' => true,
        ])->json();

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

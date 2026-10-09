<?php

namespace App\Services\Commerce;

use App\Models\Company;
use App\Models\Sale;
use App\Models\WhatsappMessage;
use App\Services\Foundation\NumberFormatService;
use App\Support\CompanyContext;
use App\Support\CustomerShare;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsappService
{
    public function __construct(
        private readonly InvoiceSheet $invoice,
        private readonly NumberFormatService $format,
        private readonly CompanyContext $context,
    ) {}

    public function configured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * Sends the approved bill template through the WhatsApp Cloud API and logs the attempt.
     */
    public function sendBill(Sale $sale, Company $company, ?int $userId): WhatsappMessage
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages(['whatsapp' => 'WhatsApp is not set up yet. Add the WhatsApp details to the .env file.']);
        }

        $sale->loadMissing('customer');
        $recipient = CustomerShare::number($sale->customer?->mobile);

        if ($recipient === null) {
            throw ValidationException::withMessages(['whatsapp' => 'This customer has no valid mobile number for WhatsApp.']);
        }

        $template = (string) config('services.whatsapp.bill_template');
        $message = WhatsappMessage::query()->create([
            'sale_id' => $sale->id,
            'recipient' => $recipient,
            'template' => $template,
            'status' => 'failed',
            'user_id' => $userId,
        ]);

        try {
            $response = Http::withToken((string) config('services.whatsapp.token'))
                ->acceptJson()
                ->timeout(20)
                ->post($this->endpoint(), [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $recipient,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => (string) config('services.whatsapp.bill_template_language')],
                        'components' => $this->components($sale, $company),
                    ],
                ]);
        } catch (ConnectionException) {
            $message->update(['error' => 'Could not reach WhatsApp. Check the internet connection and try again.', 'status_at' => now()]);

            return $message;
        }

        $providerId = $response->json('messages.0.id');

        if ($response->successful() && is_string($providerId)) {
            $message->update(['provider_id' => $providerId, 'status' => 'sent', 'status_at' => now()]);

            return $message;
        }

        $detail = $response->json('error.error_data.details') ?: $response->json('error.message') ?: 'HTTP '.$response->status();
        $code = $response->json('error.code');
        $message->update([
            'error' => Str::limit(($code ? '('.$code.') ' : '').$detail, 480),
            'status_at' => now(),
        ]);

        return $message;
    }

    /**
     * Applies a delivery update from the webhook. Status only moves forward, except failures.
     *
     * @param  array<string, mixed>  $status
     */
    public function applyStatus(array $status): void
    {
        $providerId = $status['id'] ?? null;
        $state = $status['status'] ?? null;

        if (! is_string($providerId) || ! is_string($state) || ! array_key_exists($state, WhatsappMessage::STATUS_RANK)) {
            return;
        }

        if ($state === 'failed') {
            Log::warning('WhatsApp delivery failed', ['id' => $providerId, 'to' => $status['recipient_id'] ?? null, 'errors' => $status['errors'] ?? []]);
        }

        $this->context->bypassing(function () use ($providerId, $state, $status): void {
            $message = WhatsappMessage::query()->where('provider_id', $providerId)->first();

            if ($message === null) {
                return;
            }

            if ($state !== 'failed' && WhatsappMessage::STATUS_RANK[$state] <= (WhatsappMessage::STATUS_RANK[$message->status] ?? 0)) {
                return;
            }

            $error = $status['errors'][0] ?? null;
            $message->update([
                'status' => $state,
                'error' => $state === 'failed' && is_array($error)
                    ? Str::limit((string) ($error['error_data']['details'] ?? $error['title'] ?? $error['message'] ?? 'Delivery failed'), 480)
                    : $message->error,
                'status_at' => isset($status['timestamp']) && is_numeric($status['timestamp'])
                    ? now()->setTimestamp((int) $status['timestamp'])
                    : now(),
            ]);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function components(Sale $sale, Company $company): array
    {
        $link = $this->invoice->link($sale);
        $body = [
            ['type' => 'text', 'text' => trim((string) $sale->customer?->name) ?: 'Customer'],
            ['type' => 'text', 'text' => $sale->number],
            ['type' => 'text', 'text' => $this->format->money((string) $sale->total, $company)],
        ];

        if (! config('services.whatsapp.bill_template_button')) {
            $body[] = ['type' => 'text', 'text' => $link];

            return [['type' => 'body', 'parameters' => $body]];
        }

        return [
            ['type' => 'body', 'parameters' => $body],
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => Str::after($link, '/bill/')]],
            ],
        ];
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.whatsapp.graph_url'), '/')
            .'/'.config('services.whatsapp.graph_version')
            .'/'.config('services.whatsapp.phone_number_id').'/messages';
    }
}

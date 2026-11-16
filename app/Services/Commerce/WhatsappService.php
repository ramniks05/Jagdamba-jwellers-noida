<?php

namespace App\Services\Commerce;

use App\Models\Company;
use App\Models\Sale;
use App\Models\WhatsappMessage;
use App\Support\CompanyContext;
use App\Support\CustomerShare;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsappService
{
    public function __construct(
        private readonly InvoiceSheet $invoice,
        private readonly CompanyContext $context,
    ) {}

    public function configured(): bool
    {
        if ($this->usesCrm()) {
            return filled(config('services.whatsapp.crm_url')) && filled(config('services.whatsapp.crm_token'));
        }

        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * Sends the approved bill template (CRM or Meta Cloud API) and logs the attempt.
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
            $response = $this->usesCrm()
                ? $this->sendViaCrm($sale, $company, $recipient, $template)
                : $this->sendViaMeta($sale, $company, $recipient, $template);
        } catch (ConnectionException) {
            $message->update(['error' => 'Could not reach WhatsApp. Check the internet connection and try again.', 'status_at' => now()]);

            return $message;
        }

        $providerId = $this->providerId($response);

        if ($this->sendSucceeded($response, $providerId)) {
            $message->update([
                'provider_id' => $providerId ?: 'crm-'.Str::lower(Str::random(16)),
                'status' => 'sent',
                'status_at' => now(),
                'error' => null,
            ]);

            return $message;
        }

        $detail = $this->errorDetail($response);
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

    private function usesCrm(): bool
    {
        return strtolower((string) config('services.whatsapp.provider', 'meta')) === 'crm';
    }

    private function sendViaCrm(Sale $sale, Company $company, string $recipient, string $template): Response
    {
        $values = $this->bodyValues($sale, $company);
        $payload = [
            'phone_number' => $recipient,
            'template_name' => $template,
            'template_language' => (string) config('services.whatsapp.bill_template_language'),
            'field_1' => $values[0],
            'field_2' => $values[1],
            'field_3' => $values[2],
            'contact' => [
                'first_name' => $values[0],
            ],
        ];

        if (config('services.whatsapp.bill_template_button')) {
            // Same signed /bill/{uuid}?signature=… path that the invoice QR uses.
            $payload['button_0'] = $this->billQrSuffix($sale);
        } elseif (isset($values[3])) {
            $payload['field_4'] = $values[3];
        }

        $url = (string) config('services.whatsapp.crm_url');
        $token = (string) config('services.whatsapp.crm_token');

        return Http::withToken($token)
            ->acceptJson()
            ->asForm()
            ->timeout(20)
            ->post($url.(str_contains($url, '?') ? '&' : '?').'token='.urlencode($token), $payload);
    }

    private function sendViaMeta(Sale $sale, Company $company, string $recipient, string $template): Response
    {
        return Http::withToken((string) config('services.whatsapp.token'))
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
                    'components' => $this->metaComponents($sale, $company),
                ],
            ]);
    }

    /**
     * Body variables: customer name, bill number, shop name, optional full link (no-button mode).
     *
     * @return list<string>
     */
    private function bodyValues(Sale $sale, Company $company): array
    {
        $values = [
            trim((string) $sale->customer?->name) ?: 'Customer',
            $sale->number,
            $company->displayName(),
        ];

        if (! config('services.whatsapp.bill_template_button')) {
            $values[] = $this->invoice->link($sale);
        }

        return $values;
    }

    /**
     * Dynamic end of the public bill URL — identical to the invoice QR target after /bill/.
     */
    private function billQrSuffix(Sale $sale): string
    {
        return Str::after($this->invoice->link($sale), '/bill/');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metaComponents(Sale $sale, Company $company): array
    {
        $body = array_map(
            fn (string $text) => ['type' => 'text', 'text' => $text],
            $this->bodyValues($sale, $company),
        );

        if (! config('services.whatsapp.bill_template_button')) {
            return [['type' => 'body', 'parameters' => $body]];
        }

        return [
            ['type' => 'body', 'parameters' => $body],
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $this->billQrSuffix($sale)]],
            ],
        ];
    }

    private function sendSucceeded(Response $response, ?string $providerId): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $result = strtolower((string) ($response->json('result') ?? $response->json('status') ?? ''));

        if (in_array($result, ['failed', 'error', 'false'], true)) {
            return false;
        }

        if (in_array($result, ['success', 'ok', 'sent', 'true'], true)) {
            return true;
        }

        return is_string($providerId) && $providerId !== '';
    }

    private function providerId(Response $response): ?string
    {
        foreach ([
            'id',
            'message_id',
            'data.id',
            'data.message_id',
            'messages.0.id',
        ] as $path) {
            $value = data_get($response->json(), $path);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function errorDetail(Response $response): string
    {
        $detail = $response->json('message')
            ?: $response->json('error.error_data.details')
            ?: $response->json('error.message')
            ?: $response->json('error');

        if (is_array($detail)) {
            $detail = json_encode($detail);
        }

        return is_string($detail) && $detail !== '' ? $detail : 'HTTP '.$response->status();
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.whatsapp.graph_url'), '/')
            .'/'.config('services.whatsapp.graph_version')
            .'/'.config('services.whatsapp.phone_number_id').'/messages';
    }
}

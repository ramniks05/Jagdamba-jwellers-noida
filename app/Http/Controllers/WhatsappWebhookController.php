<?php

namespace App\Http\Controllers;

use App\Services\Commerce\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $expected = (string) config('services.whatsapp.verify_token');
        $given = (string) $request->query('hub_verify_token', '');

        abort_unless(
            $request->query('hub_mode') === 'subscribe' && $expected !== '' && hash_equals($expected, $given),
            403,
        );

        return response((string) $request->query('hub_challenge', ''), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, WhatsappService $whatsapp): JsonResponse
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        abort_unless(
            $secret !== '' && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature),
            403,
        );

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    if (is_array($status)) {
                        $whatsapp->applyStatus($status);
                    }
                }
            }
        }

        return response()->json(['received' => true]);
    }
}

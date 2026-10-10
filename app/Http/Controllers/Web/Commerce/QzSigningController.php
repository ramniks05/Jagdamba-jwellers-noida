<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QzSigningController extends Controller
{
    public function certificate(): Response
    {
        $this->authorize('inventory.print');
        $certificate = $this->read(config('services.qz.certificate'));

        if ($certificate === null) {
            return response('', 204, ['Cache-Control' => 'no-store']);
        }

        return response($certificate, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function sign(Request $request): Response
    {
        $this->authorize('inventory.print');
        $data = $request->validate(['request' => ['required', 'string', 'max:10000']]);
        $pem = $this->read(config('services.qz.private_key'));

        if ($pem === null) {
            return response('Signing is not set up on this server.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $key = openssl_pkey_get_private($pem, (string) config('services.qz.private_key_passphrase'));

        if ($key === false || ! openssl_sign($data['request'], $signature, $key, OPENSSL_ALGO_SHA512)) {
            report(new \RuntimeException('QZ Tray signing key could not be used.'));

            return response('The signing key could not be used.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response(base64_encode($signature), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function read(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false || trim($contents) === '' ? null : $contents;
    }
}

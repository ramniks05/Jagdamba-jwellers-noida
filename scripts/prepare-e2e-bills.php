<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::query()->where('email', 'owner@jagdamba.test')->first();
if (! $user) {
    echo "Missing owner@jagdamba.test — run migrations/seed first.\n";
    exit(1);
}

app(App\Support\CompanyContext::class)->set($user->company);

$customer = App\Models\Customer::query()
    ->where('is_active', true)
    ->where('is_system', false)
    ->orderBy('id')
    ->first();

$scanner = app(App\Services\Commerce\BillScanService::class);
$scanCode = '';

foreach (App\Models\Item::query()
    ->where('status', App\Enums\ItemStatus::Available)
    ->where('own_gold', false)
    ->orderBy('id')
    ->cursor() as $candidate) {
    foreach (array_filter([$candidate->barcode, $candidate->item_code]) as $code) {
        if ($scanner->scan($code, $user->company)['ok'] ?? false) {
            $scanCode = $code;
            break 2;
        }
    }
}

echo json_encode([
    'email' => 'owner@jagdamba.test',
    'password' => 'ChangeMe@123',
    'customer_name' => $customer?->name ?? '',
    'customer_mobile' => $customer?->mobile ?? '',
    'scan_code' => $scanCode,
    'has_retail_tables' => App\Support\RetailBilling::enabled(),
], JSON_PRETTY_PRINT)."\n";

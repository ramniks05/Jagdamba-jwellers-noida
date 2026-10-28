<?php

/**
 * Local smoke: dummy other item + mixed bill, print invoice links.
 * Run: php scripts/local-demo-invoice.php
 */

use App\Enums\ChargeAppliesTo;
use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\RetailProduct;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Commerce\CustomerService;
use App\Services\Commerce\InvoiceSheet;
use App\Services\Commerce\ItemService;
use App\Services\Commerce\RetailProductService;
use App\Services\Commerce\SaleService;
use App\Support\CompanyContext;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = (string) config('foundation.seed.owner_email');
$owner = User::query()->where('email', $email)->where('is_active', true)->first();

if (! $owner) {
    fwrite(STDERR, "No owner user at {$email}. Run: php artisan db:seed\n");
    exit(1);
}

$company = $owner->company;
app(CompanyContext::class)->set($company);

/** @var RetailProductService $retailCatalog */
$retailCatalog = app(RetailProductService::class);
$product = RetailProduct::query()->where('code', 'DEMO-MURTI')->first();

if (! $product) {
    $product = $retailCatalog->create($company, [
        'code' => 'DEMO-MURTI',
        'name' => 'Ganesh murti (demo)',
        'default_unit_price' => '1180',
        'default_tax_mode' => 'inclusive',
        'default_tax_percent' => '3',
        'hsn_code' => '7117',
        'is_active' => true,
    ]);
    echo "Created other item: {$product->name}\n";
}

/** @var CustomerService $customers */
$customers = app(CustomerService::class);
$buyer = Customer::query()->where('name', 'Demo Buyer')->where('is_system', false)->first();

if (! $buyer) {
    $buyer = $customers->create($company, [
        'name' => 'Demo Buyer',
        'mobile' => '9999900001',
    ]);
    echo "Created customer: {$buyer->name} ({$buyer->code})\n";
}

$gold = MetalType::query()->where('code', 'GOLD')->first();
$purity = Purity::query()->where('code', '22K')->first();
$itemUuid = null;

if ($gold && $purity) {
    if (! MetalRate::query()->where('metal_type_id', $gold->id)->where('purity_id', $purity->id)->inForce()->exists()) {
        MetalRate::query()->create([
            'company_id' => $company->id,
            'branch_id' => $company->branches()->orderByDesc('is_head_office')->value('id'),
            'metal_type_id' => $gold->id,
            'purity_id' => $purity->id,
            'rate_per_gram' => '7200',
            'source' => 'manual',
            'effective_at' => now(),
        ]);
        echo "Set gold 22K rate: 7200/g\n";
    }

    $demoCode = 'DEMO-RING-01';
    $item = Item::query()->where('item_code', $demoCode)->first();

    if (! $item) {
        $location = StockLocation::query()->where('is_active', true)->orderBy('id')->first();
        $making = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Making)->where('is_active', true)->orderBy('id')->first();
        $wastage = ChargeMethod::query()->where('applies_to', ChargeAppliesTo::Wastage)->where('is_active', true)->orderBy('id')->first();
        $category = Category::query()->where('is_active', true)->orderBy('id')->first();

        if ($location && $making && $wastage && $category) {
            $item = app(ItemService::class)->create($company, [
                'name' => 'Demo gold ring',
                'item_code' => $demoCode,
                'sku' => $demoCode,
                'category_uuid' => $category->uuid,
                'metal_uuid' => $gold->uuid,
                'purity_uuid' => $purity->uuid,
                'location_uuid' => $location->uuid,
                'gross_weight' => '8.500',
                'stone_weight' => '0',
                'other_weight' => '0',
                'making_method_uuid' => $making->uuid,
                'making_value' => '400',
                'wastage_method_uuid' => $wastage->uuid,
                'wastage_value' => '5',
                'stone_value' => '0',
            ], $owner->id);
            echo "Created piece: {$item->item_code}\n";
        }
    }

    if ($item && $item->status === ItemStatus::Available) {
        $itemUuid = $item->uuid;
    }
}

/** @var SaleService $sales */
$sales = app(SaleService::class);

$payload = [
    'customer_uuid' => $buyer->uuid,
    'discount' => '0',
    'making_mode' => 'inside',
    'retail_items' => [[
        'product_uuid' => $product->uuid,
        'name' => $product->name,
        'quantity' => '1',
        'unit_price' => '1180',
        'tax_mode' => 'inclusive',
        'tax_percent' => '3',
        'tax_amount' => '34.37',
    ]],
    'payments' => [],
];

if ($itemUuid) {
    $payload['item_ids'] = [$itemUuid];
    $quote = $sales->quote(Item::query()->where('uuid', $itemUuid)->firstOrFail());
    $payload['payments'] = [['method' => 'cash', 'amount' => '80000']];
} else {
    $payload['payments'] = [['method' => 'cash', 'amount' => '1180']];
}

$sale = $sales->post($company, $payload, $owner->id);
$sale->load(['lines', 'retailLines', 'customer']);

$link = app(InvoiceSheet::class)->link($sale);
$base = rtrim((string) config('app.url'), '/');

echo "\n--- Invoice posted ---\n";
echo "Number: {$sale->number}\n";
echo "Total:  {$sale->total}\n";
echo "Lines:  ".$sale->lines->count()." jewellery, ".$sale->retailLines->count()." other\n";
echo "Staff:  {$base}/sales/{$sale->uuid}\n";
echo "Print:  sign in as {$email}, open Sales → invoice {$sale->number}\n";
echo "Customer bill link (signed):\n{$link}\n";

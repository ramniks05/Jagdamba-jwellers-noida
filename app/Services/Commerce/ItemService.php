<?php

namespace App\Services\Commerce;

use App\Enums\ChargeAppliesTo;
use App\Enums\InventoryMovement;
use App\Enums\ItemStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChargeMethod;
use App\Models\Collection;
use App\Models\Company;
use App\Models\Design;
use App\Models\Item;
use App\Models\ItemStone;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockLocation;
use App\Support\CompanyContext;
use App\Support\StoneRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemService
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes, ?int $userId = null, InventoryMovement $movement = InventoryMovement::Opening, ?Model $reference = null): Item
    {
        return DB::transaction(function () use ($company, $attributes, $userId, $movement, $reference) {
            $this->context->ensureId((int) $company->id);
            $attributes = $this->applyStoneTotals($attributes);
            $location = $this->location($attributes['location_uuid'] ?? null);
            $weights = $this->weights($attributes);
            $metalId = $this->requiredId(MetalType::class, $attributes['metal_uuid'] ?? null, 'metal_uuid');
            $purity = $this->purity($attributes['purity_uuid'] ?? null, $metalId);

            $item = Item::query()->create([
                'company_id' => $company->id,
                'branch_id' => $location->branch_id,
                'stock_location_id' => $location->id,
                'sku' => $this->code($attributes['sku'] ?? $attributes['item_code']),
                'item_code' => $this->code($attributes['item_code']),
                'barcode' => $this->blank($attributes['barcode'] ?? null),
                'rfid' => $this->blank($attributes['rfid'] ?? null),
                'name' => $attributes['name'],
                'category_id' => $this->optionalId(Category::class, $attributes['category_uuid'] ?? null, 'category_uuid'),
                'brand_id' => $this->optionalId(Brand::class, $attributes['brand_uuid'] ?? null, 'brand_uuid'),
                'collection_id' => $this->optionalId(Collection::class, $attributes['collection_uuid'] ?? null, 'collection_uuid'),
                'design_id' => $this->optionalId(Design::class, $attributes['design_uuid'] ?? null, 'design_uuid'),
                'metal_type_id' => $metalId,
                'purity_id' => $purity->id,
                'gross_weight' => $weights['gross'],
                'stone_weight' => $weights['stone'],
                'other_weight' => $weights['other'],
                'net_weight' => $weights['net'],
                'making_method_id' => $this->method($attributes['making_method_uuid'] ?? null, ChargeAppliesTo::Making, 'making_method_uuid'),
                'making_value' => $attributes['making_value'] ?? '0',
                'wastage_method_id' => $this->method($attributes['wastage_method_uuid'] ?? null, ChargeAppliesTo::Wastage, 'wastage_method_uuid'),
                'wastage_value' => $attributes['wastage_value'] ?? '0',
                'stone_value' => $attributes['stone_value'] ?? '0',
                'cost_price' => $attributes['cost_price'] ?? '0',
                'selling_price' => $attributes['selling_price'] ?? '0',
                'mrp' => $attributes['mrp'] ?? '0',
                'certificate_number' => $this->blank($attributes['certificate_number'] ?? null),
                'hallmark' => $this->blank($attributes['hallmark'] ?? null),
                'huid' => $this->blank($attributes['huid'] ?? null),
                'image_path' => $attributes['image_path'] ?? null,
                'status' => ItemStatus::Available,
                'notes' => $this->blank($attributes['notes'] ?? null),
            ]);

            $this->inventory->apply(
                $item,
                $movement,
                $reference,
                null,
                null,
                $movement === InventoryMovement::Purchase ? 'Purchased' : 'Opening stock',
                $userId,
            );
            $this->storeStones($item, $attributes);

            return $item->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Item $item, array $attributes): Item
    {
        return DB::transaction(function () use ($item, $attributes) {
            $this->context->ensureId((int) $item->company_id);
            $locked = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $stockEditable = in_array($locked->status, [ItemStatus::Available, ItemStatus::Reserved], true)
                && $locked->movements()->count() === 1;

            $locked->fill([
                'name' => $attributes['name'],
                'barcode' => $this->blank($attributes['barcode'] ?? null),
                'rfid' => $this->blank($attributes['rfid'] ?? null),
                'certificate_number' => $this->blank($attributes['certificate_number'] ?? null),
                'hallmark' => $this->blank($attributes['hallmark'] ?? null),
                'huid' => $this->blank($attributes['huid'] ?? null),
                'notes' => $this->blank($attributes['notes'] ?? null),
                'cost_price' => $attributes['cost_price'] ?? $locked->cost_price,
                'selling_price' => $attributes['selling_price'] ?? $locked->selling_price,
                'mrp' => $attributes['mrp'] ?? $locked->mrp,
            ]);

            if (array_key_exists('image_path', $attributes) && $attributes['image_path']) {
                $locked->image_path = $attributes['image_path'];
            }

            if ($stockEditable) {
                if (array_key_exists('stones', $attributes)) {
                    $attributes = $this->applyStoneTotals($attributes);
                }

                $weights = $this->weights($attributes);
                $metalId = $this->requiredId(MetalType::class, $attributes['metal_uuid'] ?? null, 'metal_uuid');
                $purity = $this->purity($attributes['purity_uuid'] ?? null, $metalId);
                $location = $this->location($attributes['location_uuid'] ?? null);
                $locked->fill([
                    'category_id' => $this->optionalId(Category::class, $attributes['category_uuid'] ?? null, 'category_uuid'),
                    'brand_id' => $this->optionalId(Brand::class, $attributes['brand_uuid'] ?? null, 'brand_uuid'),
                    'collection_id' => $this->optionalId(Collection::class, $attributes['collection_uuid'] ?? null, 'collection_uuid'),
                    'design_id' => $this->optionalId(Design::class, $attributes['design_uuid'] ?? null, 'design_uuid'),
                    'metal_type_id' => $metalId,
                    'purity_id' => $purity->id,
                    'gross_weight' => $weights['gross'],
                    'stone_weight' => $weights['stone'],
                    'other_weight' => $weights['other'],
                    'net_weight' => $weights['net'],
                    'making_method_id' => $this->method($attributes['making_method_uuid'] ?? null, ChargeAppliesTo::Making, 'making_method_uuid'),
                    'making_value' => $attributes['making_value'] ?? '0',
                    'wastage_method_id' => $this->method($attributes['wastage_method_uuid'] ?? null, ChargeAppliesTo::Wastage, 'wastage_method_uuid'),
                    'wastage_value' => $attributes['wastage_value'] ?? '0',
                    'stone_value' => $attributes['stone_value'] ?? '0',
                ]);
                $locked->save();
                $opening = $locked->movements()->first();

                if ($opening) {
                    $opening->gross_weight = $locked->gross_weight;
                    $opening->net_weight = $locked->net_weight;
                    $opening->save();
                }

                if ((int) $locked->stock_location_id !== (int) $location->id) {
                    $locked->branch_id = $location->branch_id;
                    $locked->save();
                    $this->inventory->apply($locked, InventoryMovement::Transfer, null, null, null, 'Moved', null, $location->id);
                }

                if (array_key_exists('stones', $attributes)) {
                    $locked->stones()->delete();
                    $this->storeStones($locked, $attributes);
                }
            } else {
                $locked->save();
            }

            return $locked->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{gross: string, stone: string, other: string, net: string}
     */
    private function weights(array $attributes): array
    {
        $gross = BigDecimal::of((string) ($attributes['gross_weight'] ?? '0'))->toScale(3, RoundingMode::HalfUp);
        $stone = BigDecimal::of((string) ($attributes['stone_weight'] ?? '0'))->toScale(3, RoundingMode::HalfUp);
        $other = BigDecimal::of((string) ($attributes['other_weight'] ?? '0'))->toScale(3, RoundingMode::HalfUp);

        if ($gross->isNegative() || $stone->isNegative() || $other->isNegative() || $stone->plus($other)->isGreaterThan($gross)) {
            throw ValidationException::withMessages([
                'gross_weight' => 'Gross weight must cover the stone weight and other weight.',
            ]);
        }

        return [
            'gross' => (string) $gross,
            'stone' => (string) $stone,
            'other' => (string) $other,
            'net' => (string) $gross->minus($stone)->minus($other)->toScale(3, RoundingMode::HalfUp),
        ];
    }

    /**
     * Named stones replace the single stone weight and value so each stone stays with this piece.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyStoneTotals(array $attributes): array
    {
        $rows = $this->namedStones($attributes);

        if ($rows === []) {
            return $attributes;
        }

        $weight = BigDecimal::zero();
        $value = BigDecimal::zero();

        foreach ($rows as $row) {
            $weight = $weight->plus($row['weight']);
            $value = $value->plus($row['value']);
        }

        $attributes['stone_weight'] = (string) $weight->toScale(3, RoundingMode::HalfUp);
        $attributes['stone_value'] = (string) $value->toScale(2, RoundingMode::HalfUp);

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storeStones(Item $item, array $attributes): void
    {
        foreach ($this->namedStones($attributes) as $index => $row) {
            ItemStone::query()->create([
                'company_id' => $item->company_id,
                'item_id' => $item->id,
                'name' => $row['name'],
                'weight' => $row['weight'],
                'value' => $row['value'],
                'rate' => $row['rate'],
                'rate_unit' => $row['rate_unit'],
                'position' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{name: string, weight: string, value: string, rate: ?string, rate_unit: ?string}>
     */
    private function namedStones(array $attributes): array
    {
        $rows = [];

        foreach ((array) ($attributes['stones'] ?? []) as $stone) {
            if (! is_array($stone) || trim((string) ($stone['name'] ?? '')) === '') {
                continue;
            }

            $rows[] = StoneRate::row($stone);
        }

        return $rows;
    }

    private function location(?string $uuid): StockLocation
    {
        $location = $uuid ? StockLocation::query()->where('uuid', $uuid)->where('is_active', true)->first() : null;

        if (! $location) {
            throw ValidationException::withMessages([
                'location_uuid' => 'Choose a stock location.',
            ]);
        }

        return $location;
    }

    private function purity(?string $uuid, int $metalId): Purity
    {
        $purity = $uuid ? Purity::query()->where('uuid', $uuid)->first() : null;

        if (! $purity || (int) $purity->metal_type_id !== $metalId) {
            throw ValidationException::withMessages([
                'purity_uuid' => 'Choose a purity for the selected metal.',
            ]);
        }

        return $purity;
    }

    private function method(?string $uuid, ChargeAppliesTo $appliesTo, string $key): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $method = ChargeMethod::query()->where('uuid', $uuid)->where('applies_to', $appliesTo)->first();

        if (! $method) {
            throw ValidationException::withMessages([
                $key => 'Choose a calculation method from this shop.',
            ]);
        }

        return $method->id;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function requiredId(string $class, ?string $uuid, string $key): int
    {
        $id = $this->optionalId($class, $uuid, $key);

        if (! $id) {
            throw ValidationException::withMessages([
                $key => 'Choose a record from this shop.',
            ]);
        }

        return $id;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function optionalId(string $class, ?string $uuid, string $key): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $record = $class::query()->where('uuid', $uuid)->first();

        if (! $record) {
            throw ValidationException::withMessages([
                $key => 'Choose a record from this shop.',
            ]);
        }

        return (int) $record->getKey();
    }

    private function code(mixed $value): string
    {
        return Str::upper(trim((string) $value));
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

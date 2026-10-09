<?php

namespace App\Services\Commerce;

use App\Enums\InventoryMovement;
use App\Enums\ItemStatus;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private readonly CompanyContext $context) {}

    public function apply(
        Item $item,
        InventoryMovement $type,
        ?Model $reference = null,
        ?string $rate = null,
        ?string $value = null,
        ?string $notes = null,
        ?int $userId = null,
        ?int $destinationLocationId = null,
    ): InventoryTransaction {
        return DB::transaction(function () use ($item, $type, $reference, $rate, $value, $notes, $userId, $destinationLocationId) {
            $this->context->ensureId((int) $item->company_id);
            $locked = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $onHand = $this->onHand($locked);
            $quantity = $this->quantityFor($type);
            $status = $this->nextStatus($locked, $type, $onHand);

            $movement = InventoryTransaction::query()->create([
                'company_id' => $locked->company_id,
                'item_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'type' => $type,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'quantity' => $quantity,
                'gross_weight' => $locked->gross_weight,
                'net_weight' => $locked->net_weight,
                'rate' => $rate,
                'value' => $value,
                'source_location_id' => $locked->stock_location_id,
                'destination_location_id' => $destinationLocationId ?? $locked->stock_location_id,
                'user_id' => $userId,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);

            $locked->status = $status;

            if ($type === InventoryMovement::Transfer && $destinationLocationId) {
                $locked->stock_location_id = $destinationLocationId;
            }

            $locked->save();
            $item->setRawAttributes($locked->getAttributes(), true);
            $item->syncOriginal();

            return $movement;
        });
    }

    public function onHand(Item $item): BigDecimal
    {
        $sum = $item->movements()->sum('quantity');

        return BigDecimal::of($sum === null || $sum === '' ? '0' : (string) $sum);
    }

    private function quantityFor(InventoryMovement $type): string
    {
        return match ($type) {
            InventoryMovement::Opening, InventoryMovement::Purchase, InventoryMovement::SaleReturn, InventoryMovement::AdjustmentIn => '1.000',
            InventoryMovement::Sale, InventoryMovement::Damage, InventoryMovement::Lost, InventoryMovement::PurchaseReturn => '-1.000',
            default => '0.000',
        };
    }

    private function nextStatus(Item $item, InventoryMovement $type, BigDecimal $onHand): ItemStatus
    {
        $available = $item->status === ItemStatus::Available && $onHand->isEqualTo('1');

        return match ($type) {
            InventoryMovement::Opening => $this->require(
                $item->status === ItemStatus::Available && $onHand->isZero(),
                'This piece is already in stock.',
            ) ? ItemStatus::Available : ItemStatus::Available,
            InventoryMovement::Sale => $this->require($available, 'This piece is not available to sell.')
                ? ItemStatus::Sold
                : ItemStatus::Sold,
            InventoryMovement::Damage => $this->require($available, 'Only an available piece can be marked damaged.')
                ? ItemStatus::Damaged
                : ItemStatus::Damaged,
            InventoryMovement::Lost => $this->require($available, 'Only an available piece can be marked lost.')
                ? ItemStatus::Lost
                : ItemStatus::Lost,
            InventoryMovement::Reserve => $this->require($available, 'Only an available piece can be reserved.')
                ? ItemStatus::Reserved
                : ItemStatus::Reserved,
            InventoryMovement::Release => $this->require(
                $item->status === ItemStatus::Reserved && $onHand->isEqualTo('1'),
                'This piece is not reserved.',
            ) ? ItemStatus::Available : ItemStatus::Available,
            InventoryMovement::Transfer => $this->require(
                in_array($item->status, [ItemStatus::Available, ItemStatus::Reserved], true) && $onHand->isEqualTo('1'),
                'Only stock still in the shop can be moved.',
            ) ? $item->status : $item->status,
            InventoryMovement::Purchase => $this->require(
                $item->status === ItemStatus::Available && $onHand->isZero(),
                'This piece is already in stock.',
            ) ? ItemStatus::Available : ItemStatus::Available,
            InventoryMovement::SaleReturn => $this->require(
                $item->status === ItemStatus::Sold && $onHand->isZero(),
                'Only a sold piece can come back from a sale.',
            ) ? ItemStatus::Available : ItemStatus::Available,
            InventoryMovement::PurchaseReturn => $this->require($available, 'Only an available piece can go back to the supplier.')
                ? ItemStatus::SentBack
                : ItemStatus::SentBack,
            InventoryMovement::RepairOut => $this->require($available, 'Only an available piece can be sent for repair.')
                ? ItemStatus::Repair
                : ItemStatus::Repair,
            InventoryMovement::AdjustmentIn => $this->require(
                in_array($item->status, [ItemStatus::Damaged, ItemStatus::Lost], true) && $onHand->isZero(),
                'Only a damaged or lost piece can come back into stock.',
            ) ? ItemStatus::Available : ItemStatus::Available,
            InventoryMovement::RepairIn => $this->require(
                $item->status === ItemStatus::Repair && $onHand->isEqualTo('1'),
                'This piece is not out for repair.',
            ) ? ItemStatus::Available : ItemStatus::Available,
            default => throw ValidationException::withMessages([
                'item' => 'This stock movement is not available yet.',
            ]),
        };
    }

    private function require(bool $ok, string $message): bool
    {
        if (! $ok) {
            throw ValidationException::withMessages([
                'item' => $message,
            ]);
        }

        return true;
    }
}

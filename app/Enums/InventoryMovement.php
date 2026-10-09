<?php

namespace App\Enums;

enum InventoryMovement: string
{
    case Opening = 'opening';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Transfer = 'transfer';
    case Damage = 'damage';
    case Lost = 'lost';
    case RepairOut = 'repair_out';
    case RepairIn = 'repair_in';
    case Reserve = 'reserve';
    case Release = 'release';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening stock',
            self::Sale => 'Sale',
            self::SaleReturn => 'Sales return',
            self::Purchase => 'Purchase',
            self::PurchaseReturn => 'Purchase return',
            self::AdjustmentIn => 'Back in stock',
            self::AdjustmentOut => 'Adjustment out',
            self::Transfer => 'Transfer',
            self::Damage => 'Damage',
            self::Lost => 'Lost',
            self::RepairOut => 'Sent for repair',
            self::RepairIn => 'Back from repair',
            self::Reserve => 'Reserved',
            self::Release => 'Reservation released',
        };
    }
}

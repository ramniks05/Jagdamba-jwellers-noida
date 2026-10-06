<?php

namespace App\Enums;

enum DocumentType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';
    case Receipt = 'receipt';
    case Payment = 'payment';
    case Quotation = 'quotation';
    case PurchaseOrder = 'purchase_order';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case SalesReturn = 'sales_return';
    case Repair = 'repair';
    case StockTransfer = 'stock_transfer';
    case StockAdjustment = 'stock_adjustment';
    case OldGold = 'old_gold';
    case Reservation = 'reservation';
    case Expense = 'expense';
    case Scheme = 'scheme';

    public function label(): string
    {
        return (string) config('foundation.document_types.'.$this->value.'.label', $this->value);
    }

    public function defaultPrefix(): string
    {
        return (string) config('foundation.document_types.'.$this->value.'.prefix', 'DOC');
    }

    public function defaultReset(): SequenceResetPolicy
    {
        $reset = config('foundation.document_types.'.$this->value.'.reset', SequenceResetPolicy::FinancialYear->value);

        return SequenceResetPolicy::from($reset);
    }
}

<?php

namespace App\Enums;

enum SequenceResetPolicy: string
{
    case Never = 'never';
    case Yearly = 'yearly';
    case FinancialYear = 'financial_year';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never (continuous series)',
            self::Yearly => 'Every calendar year',
            self::FinancialYear => 'Every financial year',
            self::Monthly => 'Every month',
        };
    }
}

<?php

namespace App\Services\Commerce;

final class PricingBreakdown
{
    public function __construct(
        public readonly string $linesAmount,
        public readonly string $discountAmount,
        public readonly string $taxableAmount,
        public readonly string $taxAmount,
        public readonly string $exactTotal,
        public readonly string $roundOff,
        public readonly string $total,
        public readonly string $makingMode = 'inside',
        public readonly string $makingAmount = '0.00',
        public readonly string $makingTaxPercent = '0',
        public readonly string $makingTaxAmount = '0.00',
    ) {}
}

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
    ) {}
}

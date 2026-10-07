<?php

namespace App\Services\Commerce;

final class MetalMarketQuote
{
    public function __construct(
        public readonly string $goldPerGram,
        public readonly string $silverPerGram,
        public readonly string $quotedAt,
    ) {}
}

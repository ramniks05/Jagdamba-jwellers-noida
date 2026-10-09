<?php

namespace App\Support;

/**
 * Upper bounds for typed numbers, so a slip like an extra zero is refused
 * instead of being saved into a bill or the stock.
 */
class Limits
{
    /** Grams; 100 kg covers any piece or bulk lot. */
    public const WEIGHT = 'max:99999.999';

    /** Rupees per gram of metal. */
    public const RATE = 'max:1000000';

    /** Rupees for any single amount. */
    public const MONEY = 'max:999999999.99';
}

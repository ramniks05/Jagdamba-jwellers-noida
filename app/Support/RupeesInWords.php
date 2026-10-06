<?php

namespace App\Support;

class RupeesInWords
{
    public static function format(string $amount): string
    {
        $negative = str_starts_with(trim($amount), '-');
        $plain = number_format(abs((float) $amount), 2, '.', '');
        [$rupees, $paise] = array_map('intval', explode('.', $plain));

        $words = self::indian($rupees).' Rupees';

        if ($paise > 0) {
            $words .= ' and '.self::indian($paise).' Paise';
        }

        return ($negative ? 'Minus ' : '').$words.' Only';
    }

    private static function indian(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];
        $crore = intdiv($number, 10000000);
        $number %= 10000000;
        $lakh = intdiv($number, 100000);
        $number %= 100000;
        $thousand = intdiv($number, 1000);
        $number %= 1000;

        if ($crore > 0) {
            $parts[] = self::belowThousand($crore).' Crore';
        }

        if ($lakh > 0) {
            $parts[] = self::belowThousand($lakh).' Lakh';
        }

        if ($thousand > 0) {
            $parts[] = self::belowThousand($thousand).' Thousand';
        }

        if ($number > 0) {
            $parts[] = self::belowThousand($number);
        }

        return implode(' ', $parts);
    }

    private static function belowThousand(int $number): string
    {
        $ones = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        ];
        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
        ];

        $words = [];
        if ($number >= 100) {
            $words[] = $ones[intdiv($number, 100)].' Hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $words[] = $tens[intdiv($number, 10)].($number % 10 ? ' '.$ones[$number % 10] : '');
        } elseif ($number > 0) {
            $words[] = $ones[$number];
        }

        return implode(' ', $words);
    }
}

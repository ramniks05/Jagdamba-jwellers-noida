<?php

namespace App\Support;

class CustomerShare
{
    public static function whatsapp(?string $mobile, string $text): string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        $base = strlen($digits) >= 12 ? 'https://wa.me/'.$digits : 'https://wa.me/';

        return $base.'?text='.rawurlencode($text);
    }
}

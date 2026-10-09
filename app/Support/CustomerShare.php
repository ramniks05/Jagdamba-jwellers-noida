<?php

namespace App\Support;

class CustomerShare
{
    public static function whatsapp(?string $mobile, string $text): string
    {
        $number = self::number($mobile);
        $base = $number !== null ? 'https://wa.me/'.$number : 'https://wa.me/';

        return $base.'?text='.rawurlencode($text);
    }

    /**
     * International digits WhatsApp expects (91XXXXXXXXXX), or null when the mobile cannot be used.
     */
    public static function number(?string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return strlen($digits) >= 12 && strlen($digits) <= 15 ? $digits : null;
    }
}

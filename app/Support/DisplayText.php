<?php

namespace App\Support;

class DisplayText
{
    /** Each word capitalised — used for piece names on printed bills. */
    public static function titleCase(string $text): string
    {
        $text = trim($text);

        return $text === '' ? '' : mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }
}

<?php

namespace App\Support;

class InvoiceTermsHtml
{
    /**
     * Multi-line shop terms become a numbered or bulleted list on the printed bill.
     */
    public static function format(string $terms): string
    {
        $terms = trim($terms);
        if ($terms === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $terms) ?: [];
        $lines = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), $lines),
            static fn (string $line): bool => $line !== '',
        ));

        if ($lines === []) {
            return '';
        }

        if (count($lines) === 1 && ! self::isMarkedLine($lines[0])) {
            return '<p>'.e($lines[0]).'</p>';
        }

        $ordered = self::preferOrdered($lines);
        $tag = $ordered ? 'ol' : 'ul';
        $items = array_map(
            static fn (string $line): string => '<li>'.e(self::lineText($line)).'</li>',
            $lines,
        );

        return '<'.$tag.' class="invoice-terms-list">'.implode('', $items).'</'.$tag.'>';
    }

    private static function isMarkedLine(string $line): bool
    {
        return (bool) preg_match('/^(?:\d+[.)]\s|[-•*]\s)/u', $line);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function preferOrdered(array $lines): bool
    {
        foreach ($lines as $line) {
            if (preg_match('/^\d+[.)]\s/u', $line)) {
                return true;
            }
        }

        return false;
    }

    private static function lineText(string $line): string
    {
        if (preg_match('/^\d+[.)]\s*(.+)$/u', $line, $match)) {
            return trim($match[1]);
        }

        if (preg_match('/^[-•*]\s*(.+)$/u', $line, $match)) {
            return trim($match[1]);
        }

        return $line;
    }
}

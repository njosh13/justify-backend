<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

final class FolioCounter
{
    /**
     * Para 17: a folio is 100 words; any part of a folio counts as one folio;
     * a sum or quantity of one denomination stated in figures is one word.
     */
    public static function words(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $text = preg_replace('/\b(K?[Ss]hs?\.?|KES|£|\$)\s*(?=\d)/u', '', $text) ?? $text;
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($tokens);
    }

    public static function folios(string $text): int
    {
        return (int) ceil(self::words($text) / 100);
    }
}

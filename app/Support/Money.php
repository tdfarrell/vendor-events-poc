<?php

namespace App\Support;

class Money
{
    public static function format(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }
        $neg = $cents < 0;
        $s = '$'.number_format(abs($cents) / 100, $cents % 100 === 0 ? 0 : 2);

        return $neg ? '-'.$s : $s;
    }

    /** "$12.50", "12.5", "12" → cents. Null if it isn't a valid amount. */
    public static function parse(?string $input): ?int
    {
        $clean = str_replace([',', '$', ' '], '', (string) $input);
        if ($clean === '' || ! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        return (int) round(((float) $clean) * 100);
    }
}

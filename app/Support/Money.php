<?php

namespace App\Support;

class Money
{
    public static function rand(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        $rands = $cents / 100;
        $formatted = $cents % 100 === 0
            ? number_format($rands, 0, '.', ' ')
            : number_format($rands, 2, '.', ' ');

        return 'R'.$formatted;
    }
}

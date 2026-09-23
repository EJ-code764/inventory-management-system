<?php

namespace App\Support;

class Money
{
    public static function format(string|int|float|null $amount): string
    {
        $amount ??= 0;

        $formatted = number_format(
            (float) $amount,
            config('currency.decimals', 2),
            '.',
            ','
        );

        $symbol = config('currency.symbol', '₱');

        return config('currency.position', 'before') === 'after'
            ? $formatted.' '.$symbol
            : $symbol.$formatted;
    }
}
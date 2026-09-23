<?php

return [
    'code' => env('APP_CURRENCY', 'PHP'),
    'symbol' => env('APP_CURRENCY_SYMBOL', '₱'),
    'position' => env('APP_CURRENCY_POSITION', 'before'),
    'decimals' => (int) env('APP_CURRENCY_DECIMALS', 2),
];
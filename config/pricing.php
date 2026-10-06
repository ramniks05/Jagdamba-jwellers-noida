<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bill components
    |--------------------------------------------------------------------------
    |
    | Order is the order printed on the invoice. Remove a name to skip that
    | component. Rates, making, wastage, and the GST percent come from the
    | shop's own records and settings.
    |
    */

    'components' => [
        'metal_value',
        'wastage',
        'making',
        'stone',
        'discount',
        'tax',
        'round_off',
    ],

];

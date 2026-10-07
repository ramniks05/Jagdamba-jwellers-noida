<?php

return [

    /*
    | Free India gold and silver buy prices, in rupees per gram, before GST.
    | No key. The feed allows about ten calls a minute, so a successful
    | quote is kept for a short time.
    */
    'url' => env('METAL_PRICE_URL', 'https://api.oropocket.com/public/prices'),

    'cache_minutes' => 15,

    /*
    | The feed prices one gram of 999 metal. Shop purities are scaled from this.
    */
    'reference_fineness' => '0.999',

];

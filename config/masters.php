<?php

return [

    'categories' => [
        ['code' => 'RING', 'name' => 'Ring'],
        ['code' => 'NECKLACE', 'name' => 'Necklace'],
        ['code' => 'CHAIN', 'name' => 'Chain'],
        ['code' => 'BRACELET', 'name' => 'Bracelet'],
        ['code' => 'BANGLE', 'name' => 'Bangle'],
        ['code' => 'EARRING', 'name' => 'Earrings'],
        ['code' => 'PENDANT', 'name' => 'Pendant'],
        ['code' => 'NOSEPIN', 'name' => 'Nose Pin'],
        ['code' => 'ANKLET', 'name' => 'Anklet'],
        ['code' => 'MANGALSUTRA', 'name' => 'Mangalsutra'],
        ['code' => 'DJWL', 'name' => 'Diamond Jewellery'],
        ['code' => 'SJWL', 'name' => 'Silver Jewellery'],
        ['code' => 'COIN', 'name' => 'Gold Coin'],
        ['code' => 'OTHER', 'name' => 'Other'],
    ],

    'metals' => [
        ['code' => 'GOLD', 'name' => 'Gold'],
        ['code' => 'SILVER', 'name' => 'Silver'],
        ['code' => 'PLATINUM', 'name' => 'Platinum'],
        ['code' => 'OTHER', 'name' => 'Other'],
    ],

    /*
    | Fineness is the pure-metal share, from 0 to 1.
    | 22K is stored as 0.916, which the screen shows as 91.6%.
    */
    'purities' => [
        ['metal' => 'GOLD', 'code' => '24K', 'name' => '24K', 'fineness' => '0.999000'],
        ['metal' => 'GOLD', 'code' => '22K', 'name' => '22K', 'fineness' => '0.916000'],
        ['metal' => 'GOLD', 'code' => '20K', 'name' => '20K', 'fineness' => '0.833000'],
        ['metal' => 'GOLD', 'code' => '18K', 'name' => '18K', 'fineness' => '0.750000'],
        ['metal' => 'GOLD', 'code' => '14K', 'name' => '14K', 'fineness' => '0.583000'],
        ['metal' => 'GOLD', 'code' => '10K', 'name' => '10K', 'fineness' => '0.417000'],
        ['metal' => 'SILVER', 'code' => '999', 'name' => '999', 'fineness' => '0.999000'],
        ['metal' => 'SILVER', 'code' => '925', 'name' => '925', 'fineness' => '0.925000'],
        ['metal' => 'PLATINUM', 'code' => '950', 'name' => '950', 'fineness' => '0.950000'],
    ],

    'stone_types' => [
        ['code' => 'DIAMOND', 'name' => 'Diamond'],
        ['code' => 'RUBY', 'name' => 'Ruby'],
        ['code' => 'EMERALD', 'name' => 'Emerald'],
        ['code' => 'SAPPHIRE', 'name' => 'Sapphire'],
        ['code' => 'PEARL', 'name' => 'Pearl'],
        ['code' => 'OTHER', 'name' => 'Other'],
    ],

    'stone_grades' => [
        ['kind' => 'cut', 'code' => 'EX', 'name' => 'Excellent'],
        ['kind' => 'cut', 'code' => 'VG', 'name' => 'Very Good'],
        ['kind' => 'cut', 'code' => 'GD', 'name' => 'Good'],
        ['kind' => 'color', 'code' => 'D', 'name' => 'D'],
        ['kind' => 'color', 'code' => 'E', 'name' => 'E'],
        ['kind' => 'color', 'code' => 'F', 'name' => 'F'],
        ['kind' => 'color', 'code' => 'G', 'name' => 'G'],
        ['kind' => 'color', 'code' => 'H', 'name' => 'H'],
        ['kind' => 'clarity', 'code' => 'VVS1', 'name' => 'VVS1'],
        ['kind' => 'clarity', 'code' => 'VVS2', 'name' => 'VVS2'],
        ['kind' => 'clarity', 'code' => 'VS1', 'name' => 'VS1'],
        ['kind' => 'clarity', 'code' => 'VS2', 'name' => 'VS2'],
        ['kind' => 'clarity', 'code' => 'SI1', 'name' => 'SI1'],
        ['kind' => 'certification', 'code' => 'GIA', 'name' => 'GIA'],
        ['kind' => 'certification', 'code' => 'IGI', 'name' => 'IGI'],
        ['kind' => 'certification', 'code' => 'BIS', 'name' => 'BIS'],
        ['kind' => 'certification', 'code' => 'SGL', 'name' => 'SGL'],
    ],

    'charge_methods' => [
        ['applies_to' => 'making', 'code' => 'per_gram', 'name' => 'Per gram'],
        ['applies_to' => 'making', 'code' => 'percentage', 'name' => 'Percentage'],
        ['applies_to' => 'making', 'code' => 'fixed', 'name' => 'Fixed amount'],
        ['applies_to' => 'wastage', 'code' => 'percentage', 'name' => 'Percentage'],
        ['applies_to' => 'wastage', 'code' => 'per_gram', 'name' => 'Per gram'],
        ['applies_to' => 'wastage', 'code' => 'fixed', 'name' => 'Fixed'],
    ],

];

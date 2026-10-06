<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo shop seed
    |--------------------------------------------------------------------------
    |
    | Used only by the local seeder. Production seeding is refused.
    | Rates, taxes, and invoice formulas are not stored here.
    |
    */

    'seed' => [
        'owner_name' => env('SEED_OWNER_NAME', 'Shop Owner'),
        'owner_email' => env('SEED_OWNER_EMAIL', 'owner@jagdamba.test'),
        'owner_password' => env('SEED_OWNER_PASSWORD', 'ChangeMe@123'),
    ],

    'demo_shop' => [
        'name' => 'Jagdamba Jewellers',
        'legal_name' => 'Jagdamba Jewellers',
        'code' => 'JAGDAMBA',
        'email' => 'shop@jagdamba.test',
        'phone' => '02240001234',
        'mobile' => '9876543210',
        'website' => null,
        'gstin' => '27AAPFU0939F1ZV',
        'pan' => 'AAPFU0939F',
        'address_line1' => '12 Market Road',
        'address_line2' => null,
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'postal_code' => '400001',
        'country' => 'India',
        'timezone' => 'Asia/Kolkata',
        'currency_code' => 'INR',
        'fy_start_month' => 4,
    ],

    'groups' => [
        'currency' => 'Currency',
        'datetime' => 'Date and time',
        'number' => 'Number format',
        'invoice' => 'Invoice',
        'pricing' => 'Billing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Setting catalog
    |--------------------------------------------------------------------------
    |
    | Keys are the only settings a shop can save. Defaults live here.
    | Each shop's values live in the settings table.
    |
    */

    'settings' => [
        'currency.code' => [
            'group' => 'currency',
            'label' => 'Currency code',
            'type' => 'string',
            'default' => 'INR',
            'rules' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ],
        'currency.symbol' => [
            'group' => 'currency',
            'label' => 'Currency symbol',
            'type' => 'string',
            'default' => '₹',
            'rules' => ['required', 'string', 'max:8'],
        ],
        'currency.symbol_position' => [
            'group' => 'currency',
            'label' => 'Symbol position',
            'type' => 'string',
            'default' => 'before',
            'rules' => ['required', 'string', 'in:before,after'],
            'options' => [
                'before' => 'Before the amount',
                'after' => 'After the amount',
            ],
        ],
        'currency.decimal_places' => [
            'group' => 'currency',
            'label' => 'Currency decimal places',
            'type' => 'integer',
            'default' => 2,
            'rules' => ['required', 'integer', 'between:0,4'],
        ],
        'currency.thousand_separator' => [
            'group' => 'currency',
            'label' => 'Currency thousand separator',
            'type' => 'string',
            'default' => ',',
            'rules' => ['nullable', 'string', 'max:1'],
        ],
        'currency.decimal_separator' => [
            'group' => 'currency',
            'label' => 'Currency decimal separator',
            'type' => 'string',
            'default' => '.',
            'rules' => ['required', 'string', 'size:1'],
        ],
        'datetime.timezone' => [
            'group' => 'datetime',
            'label' => 'Timezone',
            'type' => 'string',
            'default' => 'Asia/Kolkata',
            'rules' => ['required', 'timezone'],
        ],
        'datetime.date_format' => [
            'group' => 'datetime',
            'label' => 'Date format',
            'type' => 'string',
            'default' => 'd-m-Y',
            'rules' => ['required', 'string', 'in:d-m-Y,d/m/Y,Y-m-d,m/d/Y,d M Y'],
            'options' => [
                'd-m-Y' => '31-12-2026',
                'd/m/Y' => '31/12/2026',
                'Y-m-d' => '2026-12-31',
                'm/d/Y' => '12/31/2026',
                'd M Y' => '31 Dec 2026',
            ],
        ],
        'datetime.time_format' => [
            'group' => 'datetime',
            'label' => 'Time format',
            'type' => 'string',
            'default' => 'h:i A',
            'rules' => ['required', 'string', 'in:h:i A,H:i'],
            'options' => [
                'h:i A' => '02:30 PM',
                'H:i' => '14:30',
            ],
        ],
        'number.grouping' => [
            'group' => 'number',
            'label' => 'Digit grouping',
            'type' => 'string',
            'default' => 'indian',
            'rules' => ['required', 'string', 'in:indian,international'],
            'options' => [
                'indian' => 'Indian (12,34,567)',
                'international' => 'International (1,234,567)',
            ],
        ],
        'number.decimal_places' => [
            'group' => 'number',
            'label' => 'Number decimal places',
            'type' => 'integer',
            'default' => 2,
            'rules' => ['required', 'integer', 'between:0,6'],
        ],
        'number.weight_decimal_places' => [
            'group' => 'number',
            'label' => 'Weight decimal places',
            'type' => 'integer',
            'default' => 3,
            'rules' => ['required', 'integer', 'between:0,6'],
        ],
        'number.thousand_separator' => [
            'group' => 'number',
            'label' => 'Number thousand separator',
            'type' => 'string',
            'default' => ',',
            'rules' => ['nullable', 'string', 'max:1'],
        ],
        'number.decimal_separator' => [
            'group' => 'number',
            'label' => 'Number decimal separator',
            'type' => 'string',
            'default' => '.',
            'rules' => ['required', 'string', 'size:1'],
        ],
        'invoice.terms' => [
            'group' => 'invoice',
            'label' => 'Invoice terms',
            'type' => 'string',
            'input' => 'textarea',
            'default' => 'Goods once sold are subject to the shop exchange policy. Please retain this invoice.',
            'rules' => ['nullable', 'string', 'max:2000'],
        ],
        'invoice.footer_note' => [
            'group' => 'invoice',
            'label' => 'Invoice footer',
            'type' => 'string',
            'input' => 'textarea',
            'default' => 'Thank you for your business.',
            'rules' => ['nullable', 'string', 'max:500'],
        ],
        'invoice.show_logo' => [
            'group' => 'invoice',
            'label' => 'Show shop logo on invoices',
            'type' => 'boolean',
            'default' => true,
            'rules' => ['required', 'boolean'],
        ],
        'invoice.paper_size' => [
            'group' => 'invoice',
            'label' => 'Default paper',
            'type' => 'string',
            'default' => 'a4',
            'rules' => ['required', 'string', 'in:a4,thermal_80'],
            'options' => [
                'a4' => 'A4',
                'thermal_80' => 'Thermal 80mm',
            ],
        ],
        'pricing.gst_percent' => [
            'group' => 'pricing',
            'label' => 'GST percent',
            'help' => 'Used on new bills. Indian gold jewellery is usually 3. Each bill stores the percent that was used.',
            'type' => 'decimal',
            'default' => '3',
            'rules' => ['required', 'numeric', 'gte:0', 'lte:100'],
        ],
        'pricing.round_rupee' => [
            'group' => 'pricing',
            'label' => 'Round the bill to the nearest rupee',
            'type' => 'boolean',
            'default' => true,
            'rules' => ['required', 'boolean'],
        ],
        'invoice.tax_display' => [
            'group' => 'invoice',
            'label' => 'Price entry',
            'help' => 'Exclusive adds the GST percent on top. Inclusive treats the worked amount as already including that GST percent.',
            'type' => 'string',
            'default' => 'exclusive',
            'rules' => ['required', 'string', 'in:exclusive,inclusive'],
            'options' => [
                'exclusive' => 'Tax exclusive',
                'inclusive' => 'Tax inclusive',
            ],
        ],
    ],

    'document_sequence' => [
        'padding' => 4,
        'separator' => '-',
    ],

    /*
    |--------------------------------------------------------------------------
    | Document series
    |--------------------------------------------------------------------------
    |
    | Prefixes below are seed defaults. Each shop can change them.
    | Issuing a number copies the formatted value onto an allocation row
    | that is never updated.
    |
    */

    'document_types' => [
        'invoice' => ['label' => 'Sales invoice', 'prefix' => 'INV', 'reset' => 'financial_year'],
        'credit_note' => ['label' => 'Credit note', 'prefix' => 'CN', 'reset' => 'financial_year'],
        'debit_note' => ['label' => 'Debit note', 'prefix' => 'DN', 'reset' => 'financial_year'],
        'receipt' => ['label' => 'Receipt', 'prefix' => 'RCT', 'reset' => 'financial_year'],
        'payment' => ['label' => 'Payment voucher', 'prefix' => 'PAY', 'reset' => 'financial_year'],
        'quotation' => ['label' => 'Quotation', 'prefix' => 'QT', 'reset' => 'financial_year'],
        'purchase_order' => ['label' => 'Purchase order', 'prefix' => 'PO', 'reset' => 'financial_year'],
        'purchase' => ['label' => 'Purchase', 'prefix' => 'PUR', 'reset' => 'financial_year'],
        'purchase_return' => ['label' => 'Purchase return', 'prefix' => 'PRT', 'reset' => 'financial_year'],
        'sales_return' => ['label' => 'Sales return', 'prefix' => 'SRT', 'reset' => 'financial_year'],
        'repair' => ['label' => 'Repair order', 'prefix' => 'REP', 'reset' => 'financial_year'],
        'stock_transfer' => ['label' => 'Stock transfer', 'prefix' => 'STF', 'reset' => 'never'],
        'stock_adjustment' => ['label' => 'Stock adjustment', 'prefix' => 'ADJ', 'reset' => 'financial_year'],
        'old_gold' => ['label' => 'Old gold exchange', 'prefix' => 'OG', 'reset' => 'financial_year'],
        'reservation' => ['label' => 'Reservation', 'prefix' => 'RSV', 'reset' => 'financial_year'],
        'expense' => ['label' => 'Expense voucher', 'prefix' => 'EXP', 'reset' => 'financial_year'],
        'scheme' => ['label' => 'Gold scheme', 'prefix' => 'SCH', 'reset' => 'financial_year'],
        'girvi' => ['label' => 'Girvi', 'prefix' => 'GRV', 'reset' => 'financial_year'],
    ],

];

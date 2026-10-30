<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Hourly Rate (IDR)
    |--------------------------------------------------------------------------
    | The baseline price in Indonesian Rupiah per 1 hour (60 minutes) of recording.
    */
    'rate_per_hour_idr' => (int) env('QUOTA_RATE_PER_HOUR_IDR', 50000),

    /*
    |--------------------------------------------------------------------------
    | Tiered Volume Discounts
    |--------------------------------------------------------------------------
    | Discount percentages applied based on the number of hours purchased.
    */
    'tiers' => [
        ['min_hours' => 1, 'max_hours' => 2, 'discount_pct' => 0],
        ['min_hours' => 3, 'max_hours' => 5, 'discount_pct' => 10],
        ['min_hours' => 6, 'max_hours' => 10, 'discount_pct' => 20],
        ['min_hours' => 11, 'max_hours' => null, 'discount_pct' => 30],
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum and Maximum Purchase Hours
    |--------------------------------------------------------------------------
    */
    'min_hours' => 1,
    'max_hours' => 100,

    /*
    |--------------------------------------------------------------------------
    | In-Session Recording Grace Period (Minutes)
    |--------------------------------------------------------------------------
    | Emergency buffer duration granted when quota hits 0 during a live meeting
    | so the recording is not abruptly cut off.
    */
    'grace_period_minutes' => (int) env('QUOTA_GRACE_PERIOD_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Platform Bank Accounts for Manual Transfer
    |--------------------------------------------------------------------------
    */
    'bank_accounts' => [
        [
            'bank_name' => 'Bank Mandiri',
            'account_number' => '137-00-1234567-8',
            'account_holder' => 'PT Notulensi Rapat Indonesia',
        ],
        [
            'bank_name' => 'Bank BCA',
            'account_number' => '731-012-3456',
            'account_holder' => 'PT Notulensi Rapat Indonesia',
        ],
        [
            'bank_name' => 'Bank BSI',
            'account_number' => '712-345-6789',
            'account_holder' => 'PT Notulensi Rapat Indonesia',
        ],
    ],
];


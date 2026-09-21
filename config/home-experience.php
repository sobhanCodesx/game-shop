<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Home Experience Cache
    |--------------------------------------------------------------------------
    |
    | Home template/settings lookup should not hit the application database on
    | every home request. File cache is the safe zero-DB default for this
    | single-setting workload; production can switch this to redis.
    |
    */
    'cache_store' => env('HOME_EXPERIENCE_CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Template Registry
    |--------------------------------------------------------------------------
    |
    | "default" is the current PlayNexus home and must remain the fallback.
    | New templates are registered here first and become selectable only after
    | their responsive renderer is completed.
    |
    */
    'templates' => [
        'default' => [
            'label' => 'پیش‌فرض PlayNexus',
            'focus' => 'balanced',
            'description' => 'چیدمان فعلی سایت بدون تغییر؛ نقطه امن و fallback همه قالب‌ها.',
            'available' => true,
        ],
        'dual_spotlight' => [
            'label' => 'Dual Spotlight',
            'focus' => 'balanced',
            'description' => 'محصول ویژه و محتوای ویژه با وزن بصری برابر در موبایل و دسکتاپ.',
            'available' => true,
        ],
        'storefront' => [
            'label' => 'Storefront',
            'focus' => 'products',
            'description' => 'برای کاتالوگ بزرگ؛ محصول، دسته‌بندی و پیشنهاد خرید در اولویت.',
            'available' => false,
        ],
        'editorial' => [
            'label' => 'Editorial',
            'focus' => 'content',
            'description' => 'برای دوره‌هایی که محتوای رسانه‌ای از تعداد محصولات بیشتر است.',
            'available' => false,
        ],
        'hybrid_grid' => [
            'label' => 'Hybrid Grid',
            'focus' => 'balanced',
            'description' => 'چیدمان شبکه‌ای متراکم با تناوب محصول و محتوا.',
            'available' => false,
        ],
        'commerce_pulse' => [
            'label' => 'Commerce Pulse',
            'focus' => 'products',
            'description' => 'محصول‌محور با حفظ Nexus Pulse، ویدیو و رادار در ریتم صفحه.',
            'available' => false,
        ],
    ],

    'preference_map' => [
        'balanced' => 'dual_spotlight',
        'products' => 'storefront',
        'content' => 'editorial',
    ],
];

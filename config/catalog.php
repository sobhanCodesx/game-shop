<?php

return [
    'product' => [
        'types' => [
            'physical_game' => ['label' => 'دیسک بازی', 'description' => 'بازی فیزیکی با ارسال پستی', 'requires_shipping' => true, 'uses_game' => true, 'uses_platforms' => true, 'uses_condition' => true, 'allows_trade' => true],
            'digital_game' => ['label' => 'بازی دیجیتال', 'description' => 'لایسنس یا کد فعال‌سازی', 'requires_shipping' => false, 'uses_game' => true, 'uses_platforms' => true, 'uses_digital_delivery' => true],
            'game_account' => ['label' => 'اکانت بازی', 'description' => 'اکانت اختصاصی با تحویل امن', 'requires_shipping' => false, 'uses_game' => true, 'uses_platforms' => true, 'uses_digital_delivery' => true],
            'capacity_account' => ['label' => 'اکانت ظرفیتی', 'description' => 'ظرفیت قابل تعریف برای بازی', 'requires_shipping' => false, 'uses_game' => true, 'uses_platforms' => true, 'uses_digital_delivery' => true],
            'full_capacity' => ['label' => 'فول ظرفیت', 'description' => 'دسترسی کامل اکانت', 'requires_shipping' => false, 'uses_game' => true, 'uses_platforms' => true, 'uses_digital_delivery' => true],
            'gift_card' => ['label' => 'گیفت کارت', 'description' => 'کد دیجیتال متناسب با ریجن', 'requires_shipping' => false, 'uses_digital_delivery' => true],
            'console' => ['label' => 'کنسول', 'description' => 'کنسول و سخت‌افزار بازی', 'requires_shipping' => true, 'uses_platforms' => true, 'uses_condition' => true],
            'controller' => ['label' => 'کنترلر', 'description' => 'دسته و کنترلر گیمینگ', 'requires_shipping' => true, 'uses_platforms' => true, 'uses_condition' => true],
            'headset' => ['label' => 'هدست', 'description' => 'هدست و تجهیزات صوتی', 'requires_shipping' => true, 'uses_platforms' => true, 'uses_condition' => true],
            'keyboard' => ['label' => 'کیبورد', 'description' => 'کیبورد گیمینگ', 'requires_shipping' => true, 'uses_platforms' => true, 'uses_condition' => true],
            'mouse' => ['label' => 'ماوس', 'description' => 'ماوس گیمینگ', 'requires_shipping' => true, 'uses_platforms' => true, 'uses_condition' => true],
            'monitor' => ['label' => 'مانیتور', 'description' => 'نمایشگر گیمینگ', 'requires_shipping' => true, 'uses_platforms' => true, 'uses_condition' => true],
            'dlc' => ['label' => 'DLC', 'description' => 'محتوای افزودنی بازی', 'requires_shipping' => false, 'uses_game' => true, 'uses_platforms' => true, 'uses_digital_delivery' => true],
            'subscription' => ['label' => 'اشتراک', 'description' => 'اشتراک زمان‌دار سرویس', 'requires_shipping' => false, 'uses_digital_delivery' => true],
            'gaming_accessory' => ['label' => 'لوازم جانبی', 'description' => 'سایر تجهیزات گیمینگ', 'requires_shipping' => true, 'uses_platforms' => true, 'uses_condition' => true],
            'merchandise' => ['label' => 'کالای هواداری', 'description' => 'محصولات کلکسیونی و هواداری', 'requires_shipping' => true, 'uses_condition' => true],
            'service' => ['label' => 'خدمات گیمینگ', 'description' => 'خدمت قانونی نصب، راه‌اندازی یا پشتیبانی', 'requires_shipping' => false, 'uses_digital_delivery' => true],
            'other' => ['label' => 'سایر', 'description' => 'نوع محصول سفارشی', 'requires_shipping' => true, 'uses_condition' => true],
        ],
        'availability' => [
            'in_stock' => 'موجود', 'low_stock' => 'موجودی کم', 'out_of_stock' => 'ناموجود',
            'preorder' => 'پیش‌فروش', 'coming_soon' => 'به‌زودی', 'discontinued' => 'توقف تولید',
        ],
        'conditions' => ['new' => 'نو', 'used' => 'کارکرده', 'refurbished' => 'بازسازی‌شده'],
        'delivery_methods' => ['instant' => 'آنی و خودکار', 'manual' => 'دستی توسط پشتیبانی', 'scheduled' => 'زمان‌بندی‌شده'],
        'statuses' => [
            'draft' => 'پیش‌نویس', 'pending_review' => 'در انتظار بررسی', 'published' => 'منتشرشده',
            'hidden' => 'مخفی', 'archived' => 'آرشیو', 'out_of_stock' => 'ناموجود', 'disabled' => 'غیرفعال',
        ],
        'visibilities' => ['public' => 'عمومی', 'private' => 'خصوصی', 'members_only' => 'فقط اعضا', 'hidden' => 'پنهان'],
    ],
];

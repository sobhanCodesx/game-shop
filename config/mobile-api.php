<?php

return [
    'token_ttl_days' => (int) env('MOBILE_API_TOKEN_TTL_DAYS', 180),
    'token_name' => env('MOBILE_API_TOKEN_NAME', 'PlayNexus Mobile'),
    'min_app_version' => env('MOBILE_API_MIN_APP_VERSION', '1.0.0'),
    'current_app_version' => env('MOBILE_API_CURRENT_APP_VERSION', '1.0.0'),
];

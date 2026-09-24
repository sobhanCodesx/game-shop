<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have a
    | conventional file to locate this information.
    |
    */

    'cloudflare_ai' => [
        'account_id' => env('CLOUDFLARE_AI_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_AI_API_TOKEN'),
        'gateway_id' => env('CLOUDFLARE_AI_GATEWAY_ID'),
        'model' => env('CLOUDFLARE_AI_MODEL', '@cf/openai/gpt-oss-120b'),
        'gateway_model' => env('CLOUDFLARE_AI_GATEWAY_MODEL'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
    ],

    'gemini' => [
        'cloudflare_model' => env('GEMINI_CLOUDFLARE_MODEL', 'google/gemini-3-flash'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'nexus_ai_compatible' => [
        'api_key' => env('NEXUS_AI_COMPATIBLE_API_KEY'),
        'model' => env('NEXUS_AI_COMPATIBLE_MODEL'),
        'base_url' => env('NEXUS_AI_COMPATIBLE_BASE_URL'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'posthog' => [
        // The browser project token is intentionally public. Keep it
        // overridable so the analytics project can be rotated without code.
        'token' => env('POSTHOG_PROJECT_TOKEN', 'phc_xNKKPkHpEdjcBdEtiv69CCY6UiakyQfwU7cPWEb6T7Bg'),
        'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),
    ],

    'amplitude' => [
        // Amplitude browser API keys are public project identifiers, not secrets.
        'api_key' => env('AMPLITUDE_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payamak_panel' => [
        'base_url' => env('PAYAMAK_PANEL_BASE_URL', 'https://rest.payamak-panel.com/api/SmartSMS'),
        'pattern_endpoint' => env('PAYAMAK_PANEL_PATTERN_ENDPOINT', 'https://rest.payamak-panel.com/api/SmartSMS/SendByBaseNumber'),
        'username' => env('PAYAMAK_PANEL_USERNAME'),
        'api_key' => env('PAYAMAK_PANEL_API_KEY'),
        'from' => env('PAYAMAK_PANEL_FROM'),
        'from_support_one' => env('PAYAMAK_PANEL_FROM_SUPPORT_ONE'),
        'from_support_two' => env('PAYAMAK_PANEL_FROM_SUPPORT_TWO'),
        'connect_timeout' => 2,
        'timeout' => 5,
    ],

    'sms_ir' => [
        'base_url' => env('SMS_IR_BASE_URL', 'https://api.sms.ir/v1'),
        'api_key' => env('SMS_IR_API_KEY'),
        'line_number' => env('SMS_IR_LINE_NUMBER'),
        'connect_timeout' => 2,
        'timeout' => 5,
    ],

    'expo_push' => [
        'enabled' => env('EXPO_PUSH_ENABLED', false),
        'url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'connect_timeout' => 3,
        'timeout' => 10,
    ],

    'firebase_messaging' => [
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'connect_timeout' => 3,
        'timeout' => 10,
    ],

];

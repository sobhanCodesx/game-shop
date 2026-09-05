<?php

return [
    'export_enabled' => (bool) env('DEPLOY_EXPORT_ENABLED', false),
    'import_enabled' => (bool) env('DEPLOY_IMPORT_ENABLED', false),
    'app_id' => env('DEPLOYMENT_APP_ID'),
    'signing_key' => env('DEPLOYMENT_SIGNING_KEY'),
    'composer_phar' => env('DEPLOYMENT_COMPOSER_PHAR'),
    'protocol_version' => 1,
    'schema_version' => 1,
    'chunk_size' => (int) env('DEPLOYMENT_CHUNK_SIZE', 4 * 1024 * 1024),
    'max_package_size' => (int) env('DEPLOYMENT_MAX_PACKAGE_SIZE', 536870912),
    'max_entries' => (int) env('DEPLOYMENT_MAX_ENTRIES', 30000),
    'max_extracted_size' => (int) env('DEPLOYMENT_MAX_EXTRACTED_SIZE', 1610612736),
    'max_compression_ratio' => (float) env('DEPLOYMENT_MAX_COMPRESSION_RATIO', 100),
    'token_ttl' => (int) env('DEPLOYMENT_TOKEN_TTL', 3600),
    'retention' => (int) env('DEPLOYMENT_RETENTION', 3),
    'directory' => storage_path('app/deployments'),
    'allowed_roots' => ['app', 'bootstrap', 'config', 'database/migrations', 'routes', 'resources/views', 'lang', 'public', 'vendor','storage'],
    'allowed_files' => ['composer.json', 'deployment-manifest.json', 'deployment-manifest.sig'],
];

<?php

$contentAgentToken = trim((string) env('PLAYNEXUS_CONTENT_AGENT_TOKEN', ''));
$automationConfigured = $contentAgentToken !== '';
$derivedSigningKey = $automationConfigured
    ? hash_hmac('sha256', 'playnexus/deployment-package/v1', $contentAgentToken)
    : '';

return [
    /*
     * Deployment automation intentionally reuses the existing PlayNexus content
     * agent credential. No second production secret or enable/disable flag is
     * required. If the content-agent token is absent, package import/export are
     * disabled automatically.
     */
    'export_enabled' => $automationConfigured,
    'import_enabled' => $automationConfigured,

    /*
     * A fixed application identifier prevents a package built for another
     * application from being accepted. The package-signing key is derived from
     * the existing content-agent token; the raw token is never written into a
     * package or manifest.
     */
    'app_id' => 'playnexus-production-v1',
    'signing_key' => $derivedSigningKey,

    // Kept for backwards-compatible local tooling only; no production setting
    // is required for automated GitHub deployments.
    'composer_phar' => env('DEPLOYMENT_COMPOSER_PHAR'),

    'protocol_version' => 1,
    'schema_version' => 1,
    'chunk_size' => 2 * 1024 * 1024,
    'max_package_size' => 536870912,
    'max_entries' => 30000,
    'max_extracted_size' => 1610612736,
    'max_compression_ratio' => 100,
    'token_ttl' => 3600,
    'retention' => 3,
    'directory' => storage_path('app/deployments'),
    'ssr_bundle_destination' => env('INERTIA_SSR_PASSENGER_BUNDLE_PATH'),
    'ssr_restart_file' => env('INERTIA_SSR_PASSENGER_RESTART_FILE'),
    'allowed_roots' => [
        'app',
        'bootstrap',
        'config',
        'database/migrations',
        'routes',
        'resources/views',
        'lang',
        'public',
        'vendor',
    ],
    'allowed_files' => [
        'composer.json',
        'SSR_DEPLOYMENT.md',
        'deployment-manifest.json',
        'deployment-manifest.sig',
    ],
];

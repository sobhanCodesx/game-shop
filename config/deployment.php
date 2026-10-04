<?php

$contentAgentToken = trim((string) env('PLAYNEXUS_CONTENT_AGENT_TOKEN', ''));
$automationConfigured = $contentAgentToken !== '';

$legacyExportEnabled = filter_var(
    env('DEPLOY_EXPORT_ENABLED', false),
    FILTER_VALIDATE_BOOL,
);
$legacyImportEnabled = filter_var(
    env('DEPLOY_IMPORT_ENABLED', false),
    FILTER_VALIDATE_BOOL,
);
$legacySigningKey = trim((string) env('DEPLOYMENT_SIGNING_KEY', ''));
$legacyAppId = trim((string) env('DEPLOYMENT_APP_ID', ''));

$signingKey = $automationConfigured
    ? hash_hmac('sha256', 'playnexus/deployment-package/v1', $contentAgentToken)
    : $legacySigningKey;

$appId = $automationConfigured
    ? 'playnexus-production-v1'
    : ($legacyAppId !== '' ? $legacyAppId : 'playnexus-production-v1');

return [
    /*
     * GitHub automation reuses the existing PlayNexus content-agent token.
     * The legacy local/manual deployment variables remain supported so the
     * existing deployment:export workflow keeps working unchanged.
     */
    'export_enabled' => $automationConfigured || $legacyExportEnabled,
    'import_enabled' => $automationConfigured || $legacyImportEnabled,

    /*
     * Automated packages use a deployment-specific signing key derived from
     * the content-agent root secret. Local/manual exports fall back to the
     * existing deployment signing key when that root secret is not present.
     */
    'app_id' => $appId,
    'signing_key' => $signingKey,

    'composer_phar' => env('DEPLOYMENT_COMPOSER_PHAR'),

    'protocol_version' => 1,
    'schema_version' => 1,

    // Shared-host automation uses 1 MiB chunks. Existing local overrides are
    // still honored when present.
    'chunk_size' => (int) env('DEPLOYMENT_CHUNK_SIZE', 1024 * 1024),

    'max_package_size' => (int) env('DEPLOYMENT_MAX_PACKAGE_SIZE', 536870912),
    'max_entries' => (int) env('DEPLOYMENT_MAX_ENTRIES', 30000),
    'max_extracted_size' => (int) env('DEPLOYMENT_MAX_EXTRACTED_SIZE', 1610612736),
    'max_compression_ratio' => (float) env('DEPLOYMENT_MAX_COMPRESSION_RATIO', 100),
    'token_ttl' => (int) env('DEPLOYMENT_TOKEN_TTL', 3600),

    // Shared hosting has a finite quota. Keep only the latest successful
    // deployment and aggressively reclaim abandoned upload directories.
    'retention' => (int) env('DEPLOYMENT_RETENTION', 1),
    'stale_upload_minutes' => (int) env('DEPLOYMENT_STALE_UPLOAD_MINUTES', 15),
    'failed_retention_minutes' => (int) env('DEPLOYMENT_FAILED_RETENTION_MINUTES', 5),
    'min_free_disk_bytes' => (int) env('DEPLOYMENT_MIN_FREE_DISK_BYTES', 134217728),
    'legacy_log_max_bytes' => (int) env('DEPLOYMENT_LEGACY_LOG_MAX_BYTES', 16777216),

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

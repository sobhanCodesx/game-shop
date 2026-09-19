<?php

return [
    'token' => env('PLAYNEXUS_CONTENT_AGENT_TOKEN'),
    'author_user_id' => env('PLAYNEXUS_CONTENT_AGENT_AUTHOR_USER_ID'),
    'allow_publish' => env('PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH', false),
    'allow_destructive' => env('PLAYNEXUS_CONTENT_AGENT_ALLOW_DESTRUCTIVE', false),
    'allow_uploads' => env('PLAYNEXUS_CONTENT_AGENT_ALLOW_UPLOADS', false),
    'graphql' => [
        'enabled' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_ENABLED', true),
        'allow_introspection' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_INTROSPECTION', true),
        'max_query_bytes' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_QUERY_BYTES', 48000),
        'max_variables_bytes' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_VARIABLES_BYTES', 96000),
        'max_response_bytes' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_RESPONSE_BYTES', 4194304),
        'max_depth' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_DEPTH', 14),
        'max_introspection_depth' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_INTROSPECTION_DEPTH', 20),
        'max_complexity' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_COMPLEXITY', 1500),
        'max_fields' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_FIELDS', 600),
        'max_page_size' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_PAGE_SIZE', 100),
        'max_offset' => env('PLAYNEXUS_CONTENT_AGENT_GRAPHQL_MAX_OFFSET', 50000),
    ],
    'uploads' => [
        'max_size' => env('PLAYNEXUS_CONTENT_AGENT_MAX_UPLOAD_SIZE', 104857600),
        'max_chunk_size' => env('PLAYNEXUS_CONTENT_AGENT_MAX_CHUNK_SIZE', 2097152),
        'ttl_seconds' => env('PLAYNEXUS_CONTENT_AGENT_UPLOAD_TTL', 86400),
    ],
];

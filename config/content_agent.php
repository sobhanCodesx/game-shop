<?php

return [
    'token' => env('PLAYNEXUS_CONTENT_AGENT_TOKEN'),
    'author_user_id' => env('PLAYNEXUS_CONTENT_AGENT_AUTHOR_USER_ID'),
    'allow_publish' => env('PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH', false),
    'allow_destructive' => env('PLAYNEXUS_CONTENT_AGENT_ALLOW_DESTRUCTIVE', false),
    'allow_uploads' => env('PLAYNEXUS_CONTENT_AGENT_ALLOW_UPLOADS', false),
    'uploads' => [
        'max_size' => env('PLAYNEXUS_CONTENT_AGENT_MAX_UPLOAD_SIZE', 104857600),
        'max_chunk_size' => env('PLAYNEXUS_CONTENT_AGENT_MAX_CHUNK_SIZE', 2097152),
        'ttl_seconds' => env('PLAYNEXUS_CONTENT_AGENT_UPLOAD_TTL', 86400),
    ],
];

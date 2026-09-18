<?php

return [
    'token' => env('PLAYNEXUS_CONTENT_AGENT_TOKEN'),
    'author_user_id' => env('PLAYNEXUS_CONTENT_AGENT_AUTHOR_USER_ID'),
    'allow_publish' => env('PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH', false),
    'allow_destructive' => env('PLAYNEXUS_CONTENT_AGENT_ALLOW_DESTRUCTIVE', false),
];

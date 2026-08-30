<?php

return [
    'seed' => [
        'email' => env('ADMIN_EMAIL') ?: 'admin@inertia.test',
        'password' => env('ADMIN_PASSWORD') ?: 'Admin@123456',
    ],
];

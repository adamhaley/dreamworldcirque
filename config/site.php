<?php

return [
    'noindex' => (bool) env('APP_NOINDEX', false),

    'seed_admin' => [
        'name' => env('SEED_ADMIN_NAME'),
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],
];

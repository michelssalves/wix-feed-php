<?php

return [
    'app_name' => 'Wix Feed PHP',
    'app_url' => 'https://gbxstrategy.com',
    'timezone' => 'America/Sao_Paulo',
    'session' => [
        'name' => 'wix_feed_session',
        'secure' => false,
        'same_site' => 'Lax',
        'http_only' => true,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'controle_funeraria',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'google' => [
        'client_id' => 'SEU_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
    ],
    'upload' => [
        'directory' => __DIR__ . '/../../public/uploads',
        'public_path' => 'uploads',
        'max_size' => 2 * 1024 * 1024,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ],
];

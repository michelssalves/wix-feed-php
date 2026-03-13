<?php

declare(strict_types=1);

function startAppSession(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionConfig = $config['session'];

    session_name($sessionConfig['name'] ?? 'wix_feed_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (bool) ($sessionConfig['secure'] ?? false),
        'httponly' => (bool) ($sessionConfig['http_only'] ?? true),
        'samesite' => $sessionConfig['same_site'] ?? 'Lax',
    ]);

    session_start();
}

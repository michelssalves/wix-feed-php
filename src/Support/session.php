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

function setFlashValue(string $key, mixed $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

function getFlashValue(string $key, mixed $default = null): mixed
{
    if (!isset($_SESSION['_flash']) || !array_key_exists($key, $_SESSION['_flash'])) {
        return $default;
    }

    $value = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    if ($_SESSION['_flash'] === []) {
        unset($_SESSION['_flash']);
    }

    return $value;
}

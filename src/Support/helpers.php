<?php

declare(strict_types=1);

function basePath(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path ? $base . DIRECTORY_SEPARATOR . ltrim($path, '\\/') : $base;
}

function config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $configFile = basePath('src/Config/config.php');

    if (!file_exists($configFile)) {
        throw new RuntimeException('Arquivo src/Config/config.php nao encontrado. Copie config.example.php e ajuste os dados.');
    }

    $config = require $configFile;
    return $config;
}

function appUrl(string $path = ''): string
{
    $config = config();
    $base = rtrim($config['app_url'], '/');
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        jsonResponse(['success' => false, 'message' => 'Corpo JSON invalido.'], 422);
    }

    return $decoded;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function normalizedAuthorName(?string $manualName, ?array $sessionUser): string
{
    $manualName = trim((string) $manualName);

    if ($manualName !== '') {
        return mb_substr($manualName, 0, 120);
    }

    if ($sessionUser && !empty($sessionUser['name'])) {
        return $sessionUser['name'];
    }

    return '';
}

function normalizedAuthorPhoto(?array $sessionUser): ?string
{
    return $sessionUser['photo_url'] ?? null;
}

function isGoogleConfigured(): bool
{
    $clientId = trim((string) (config()['google']['client_id'] ?? ''));
    return $clientId !== '' && !str_starts_with($clientId, 'SEU_GOOGLE_CLIENT_ID');
}

function sanitizeRichText(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $html = preg_replace('#<(script|style)[^>]*>.*?</\\1>#is', '', $html) ?? '';
    $html = preg_replace('/\son\w+="[^"]*"/i', '', $html) ?? '';
    $html = preg_replace("/\son\w+='[^']*'/i", '', $html) ?? '';
    $html = preg_replace('/javascript:/i', '', $html) ?? '';
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li>');

    return trim($html);
}

function richTextToPlainText(string $html): string
{
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return trim($text);
}

function normalizedMemorialKey(?string $value): string
{
    $value = trim((string) $value);
    $value = mb_strtolower($value);
    $value = preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';
    return mb_substr($value, 0, 120);
}

function requestMemorialKey(): string
{
    $candidates = [
        $_POST['memorial_key'] ?? null,
        $_GET['memorial_key'] ?? null,
        $_GET['memorial'] ?? null,
        $_GET['falecido'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $key = normalizedMemorialKey(is_string($candidate) ? $candidate : null);
        if ($key !== '') {
            return $key;
        }
    }

    return '';
}

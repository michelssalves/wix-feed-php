<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\GoogleAuthService;

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metodo nao permitido.'], 405);
}

$payload = requestJson();
$credential = trim((string) ($payload['credential'] ?? ''));

if ($credential === '') {
    jsonResponse(['success' => false, 'message' => 'Token Google nao informado.'], 422);
}

try {
    $pdo = Database::connection($config);
    $service = new GoogleAuthService($config, $pdo);
    $user = $service->authenticate($credential);

    $_SESSION['user'] = $user;

    jsonResponse([
        'success' => true,
        'message' => 'Login com Google realizado com sucesso.',
        'data' => ['user' => $user],
    ]);
} catch (Throwable $throwable) {
    jsonResponse([
        'success' => false,
        'message' => $throwable->getMessage(),
    ], 422);
}

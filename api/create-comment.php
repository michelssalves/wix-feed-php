<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\FeedService;
use App\Services\MemorialService;

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metodo nao permitido.'], 405);
}

$payload = requestJson();
$sessionUser = currentUser();
$postId = (int) ($payload['post_id'] ?? 0);
$memorialKey = normalizedMemorialKey((string) ($payload['memorial_key'] ?? ''));
$manualName = trim((string) ($payload['author_name'] ?? ''));
$text = trim((string) ($payload['text'] ?? ''));
$authorName = normalizedAuthorName($manualName, $sessionUser);

if ($postId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Postagem invalida.'], 422);
}

if ($memorialKey === '') {
    jsonResponse(['success' => false, 'message' => 'Memorial nao informado.'], 422);
}

if ($authorName === '') {
    jsonResponse(['success' => false, 'message' => 'Informe seu nome ou faça login com Google.'], 422);
}

if ($text === '') {
    jsonResponse(['success' => false, 'message' => 'O comentario nao pode estar vazio.'], 422);
}

try {
    $pdo = Database::connection($config);
    $memorialService = new MemorialService($pdo);
    if (!$memorialService->exists($memorialKey)) {
        jsonResponse(['success' => false, 'message' => 'Memorial nao encontrado.'], 422);
    }

    $feedService = new FeedService($pdo);

    if (!$feedService->postExists($postId, $memorialKey)) {
        jsonResponse(['success' => false, 'message' => 'Postagem nao encontrada.'], 404);
    }

    $feedService->createComment(
        $postId,
        $sessionUser['id'] ?? null,
        $authorName,
        normalizedAuthorPhoto($sessionUser),
        $text
    );

    jsonResponse(['success' => true, 'message' => 'Comentario enviado com sucesso.']);
} catch (Throwable $throwable) {
    jsonResponse(['success' => false, 'message' => $throwable->getMessage()], 422);
}

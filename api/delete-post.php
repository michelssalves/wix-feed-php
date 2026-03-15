<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\FeedService;

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metodo nao permitido.'], 405);
}

$sessionUser = currentUser();
if (!$sessionUser || empty($sessionUser['id'])) {
    jsonResponse(['success' => false, 'message' => 'Voce precisa estar logado com Google para excluir esta postagem.'], 401);
}

$payload = requestJson();
$postId = (int) ($payload['post_id'] ?? 0);

if ($postId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Postagem invalida.'], 422);
}

try {
    $pdo = Database::connection($config);
    $feedService = new FeedService($pdo);
    if (!$feedService->postExistsForUser($postId, (int) $sessionUser['id'])) {
        jsonResponse(['success' => false, 'message' => 'Voce nao pode excluir esta postagem.'], 403);
    }

    $feedService->deletePost($postId, (int) $sessionUser['id']);

    jsonResponse(['success' => true, 'message' => 'Postagem excluida com sucesso.']);
} catch (Throwable $throwable) {
    jsonResponse(['success' => false, 'message' => $throwable->getMessage()], 422);
}

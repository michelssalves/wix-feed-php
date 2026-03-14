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
    jsonResponse(['success' => false, 'message' => 'Voce precisa estar logado com Google para editar este comentario.'], 401);
}

$payload = requestJson();
$commentId = (int) ($payload['comment_id'] ?? 0);
$text = trim((string) ($payload['text'] ?? ''));

if ($commentId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Comentario invalido.'], 422);
}

if ($text === '') {
    jsonResponse(['success' => false, 'message' => 'O comentario nao pode ficar vazio.'], 422);
}

try {
    $pdo = Database::connection($config);
    $feedService = new FeedService($pdo);

    if (!$feedService->commentExistsForUser($commentId, (int) $sessionUser['id'])) {
        jsonResponse(['success' => false, 'message' => 'Voce nao pode editar este comentario.'], 403);
    }

    $feedService->updateComment($commentId, (int) $sessionUser['id'], $text);

    jsonResponse(['success' => true, 'message' => 'Comentario atualizado com sucesso.']);
} catch (Throwable $throwable) {
    jsonResponse(['success' => false, 'message' => $throwable->getMessage()], 422);
}

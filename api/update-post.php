<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\FeedService;
use App\Services\MemorialService;

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metodo nao permitido.'], 405);
}

$sessionUser = currentUser();
if (!$sessionUser || empty($sessionUser['id'])) {
    jsonResponse(['success' => false, 'message' => 'Voce precisa estar logado com Google para editar esta postagem.'], 401);
}

$payload = requestJson();
$postId = (int) ($payload['post_id'] ?? 0);
$memorialKey = normalizedMemorialKey((string) ($payload['memorial_key'] ?? ''));
$text = sanitizeRichText((string) ($payload['text'] ?? ''));

if ($postId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Postagem invalida.'], 422);
}

if ($memorialKey === '') {
    jsonResponse(['success' => false, 'message' => 'Memorial nao informado.'], 422);
}

if ($text === '' || richTextToPlainText($text) === '') {
    jsonResponse(['success' => false, 'message' => 'A postagem nao pode ficar vazia.'], 422);
}

try {
    $pdo = Database::connection($config);
    $memorialService = new MemorialService($pdo);
    if (!$memorialService->exists($memorialKey)) {
        jsonResponse(['success' => false, 'message' => 'Memorial nao encontrado.'], 422);
    }

    $feedService = new FeedService($pdo);
    if (!$feedService->postExistsForUser($postId, (int) $sessionUser['id'])) {
        jsonResponse(['success' => false, 'message' => 'Voce nao pode editar esta postagem.'], 403);
    }

    $feedService->updatePostText($postId, (int) $sessionUser['id'], $text);

    jsonResponse(['success' => true, 'message' => 'Postagem atualizada com sucesso.']);
} catch (Throwable $throwable) {
    jsonResponse(['success' => false, 'message' => $throwable->getMessage()], 422);
}

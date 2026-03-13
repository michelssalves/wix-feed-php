<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\FeedService;
use App\Services\UploadService;

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metodo nao permitido.'], 405);
}

$sessionUser = currentUser();
$manualName = trim((string) ($_POST['author_name'] ?? ''));
$text = sanitizeRichText((string) ($_POST['text'] ?? ''));
$authorName = normalizedAuthorName($manualName, $sessionUser);
$memorialKey = requestMemorialKey();
$hasImage = isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if ($memorialKey === '') {
    jsonResponse(['success' => false, 'message' => 'Memorial nao informado.'], 422);
}

if ($authorName === '') {
    jsonResponse(['success' => false, 'message' => 'Informe seu nome ou faça login com Google.'], 422);
}

if (($text === '' || richTextToPlainText($text) === '') && !$hasImage) {
    jsonResponse(['success' => false, 'message' => 'A postagem precisa ter texto ou imagem.'], 422);
}

try {
    $uploadService = new UploadService($config['upload']);
    $imagePath = $uploadService->uploadImage($_FILES['image'] ?? []);

    $pdo = Database::connection($config);
    $feedService = new FeedService($pdo);
    $feedService->createPost(
        $memorialKey,
        $sessionUser['id'] ?? null,
        $authorName,
        normalizedAuthorPhoto($sessionUser),
        $text,
        $imagePath
    );

    jsonResponse(['success' => true, 'message' => 'Postagem criada com sucesso.']);
} catch (Throwable $throwable) {
    jsonResponse(['success' => false, 'message' => $throwable->getMessage()], 422);
}

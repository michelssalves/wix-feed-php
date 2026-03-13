<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\FeedService;

require_once __DIR__ . '/bootstrap.php';

try {
    $pdo = Database::connection($config);
    $service = new FeedService($pdo);
    $memorialKey = requestMemorialKey();

    jsonResponse([
        'success' => true,
        'data' => [
            'current_user' => currentUser(),
            'google_enabled' => isGoogleConfigured(),
            'memorial_key' => $memorialKey,
            'posts' => $service->feed($memorialKey),
        ],
    ]);
} catch (Throwable $throwable) {
    jsonResponse([
        'success' => false,
        'message' => $throwable->getMessage(),
    ], 500);
}

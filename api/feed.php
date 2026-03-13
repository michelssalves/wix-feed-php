<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\FeedService;
use App\Services\MemorialService;

require_once __DIR__ . '/bootstrap.php';

try {
    $pdo = Database::connection($config);
    $service = new FeedService($pdo);
    $memorialService = new MemorialService($pdo);
    $memorialKey = requestMemorialKey();
    $memorialExists = $memorialKey === '' ? false : $memorialService->exists($memorialKey);

    jsonResponse([
        'success' => true,
        'data' => [
            'current_user' => currentUser(),
            'google_enabled' => isGoogleConfigured(),
            'memorial_key' => $memorialKey,
            'memorial_exists' => $memorialExists,
            'posts' => $memorialExists ? $service->feed($memorialKey) : [],
        ],
    ]);
} catch (Throwable $throwable) {
    jsonResponse([
        'success' => false,
        'message' => $throwable->getMessage(),
    ], 500);
}

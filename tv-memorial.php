<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Support/helpers.php';
require_once __DIR__ . '/src/Support/session.php';
require_once __DIR__ . '/src/Services/Database.php';
require_once __DIR__ . '/src/Services/MemorialService.php';

use App\Services\Database;
use App\Services\MemorialService;

try {
    $config = config();
    date_default_timezone_set($config['timezone'] ?? 'UTC');
    startAppSession($config);
    $memorialKey = requestMemorialKey();
    $pdo = Database::connection($config);
    $memorialService = new MemorialService($pdo);
    $memorial = $memorialKey !== '' ? $memorialService->findByKey($memorialKey) : null;
    $themeCss = memorialThemeCssVariables($memorial);
} catch (Throwable $throwable) {
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <title>Erro de configuracao</title>
        <style>
            body { font-family: Arial, sans-serif; background: #111; color: #f5f5f5; padding: 24px; }
            .box { max-width: 760px; margin: 40px auto; padding: 24px; border: 1px solid #444; background: #1b1b1b; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Configuracao pendente</h1>
            <p><?= e($throwable->getMessage()) ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mural TV | <?= e($memorial['nome_falecido'] ?? $config['app_name']) ?></title>
    <link rel="stylesheet" href="./assets/css/styles.css">
    <style><?= $themeCss ?? memorialThemeCssVariables(null) ?></style>
</head>
<body class="tv-body">
<main class="tv-app">
    <header class="tv-header">
        <div class="tv-header__copy">
            <p class="tv-header__eyebrow">Mural de homenagens</p>
            <h1 class="tv-header__title"><?= e(($memorial['nome_falecido'] ?? '') !== '' ? $memorial['nome_falecido'] : 'Memorial') ?></h1>
            <p class="tv-header__text">Mensagens exibidas automaticamente para acompanhamento em TV.</p>
        </div>
        <div class="tv-header__actions">
            <span class="tv-status" id="tv-status">Aguardando mensagens</span>
            <button class="tv-fullscreen-button" id="tv-fullscreen-button" type="button">Tela cheia</button>
        </div>
    </header>

    <section class="tv-stage-shell">
        <div class="tv-stage" id="tv-stage">
            <div class="tv-empty-state" id="tv-empty-state">
                <strong>Nenhuma mensagem ainda.</strong>
                <span>Assim que alguem publicar no memorial, ela aparecera aqui automaticamente.</span>
            </div>
        </div>
    </section>

    <footer class="tv-footer">
        <div class="tv-progress" id="tv-progress"></div>
        <div class="tv-footer__meta">
            <span id="tv-counter">0 mensagens</span>
            <span id="tv-last-update">Atualizando...</span>
        </div>
    </footer>
</main>

<script>
    window.TV_APP_CONFIG = {
        apiBase: <?= json_encode(appUrl('api'), JSON_UNESCAPED_SLASHES) ?>,
        appBase: <?= json_encode(appUrl(), JSON_UNESCAPED_SLASHES) ?>,
        memorialKey: <?= json_encode($memorialKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        memorialExists: <?= json_encode((bool) $memorial) ?>,
        memorialName: <?= json_encode($memorial['nome_falecido'] ?? 'Memorial', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="./assets/js/tv.js" defer></script>
</body>
</html>

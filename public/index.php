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
    $currentUser = currentUser();
    $googleClientId = trim((string) ($config['google']['client_id'] ?? ''));
    $googleEnabled = isGoogleConfigured();
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
            <p>Copie <strong>src/Config/config.example.php</strong> para <strong>src/Config/config.php</strong> e ajuste os dados.</p>
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
    <title><?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
    <link rel="stylesheet" href="./assets/css/styles.css">
    <style><?= $themeCss ?? memorialThemeCssVariables(null) ?></style>
</head>
<body>
<main class="app-shell">
    <section class="composer">
        <div id="flash-message" class="flash-message" hidden></div>

        <div id="user-session" class="user-session<?= $currentUser ? '' : ' is-hidden' ?>">
            <div class="session-main">
                <div id="session-avatar"></div>
                <div>
                    <strong id="session-name"><?= e($currentUser['name'] ?? '') ?></strong>
                </div>
            </div>
            <button id="logout-button" class="logout-text-button" type="button">Sair</button>
        </div>

        <form id="post-form" class="post-form" enctype="multipart/form-data">
            <input type="hidden" id="memorial-key" name="memorial_key" value="<?= e($memorialKey) ?>">

            <div class="tribute-composer-card">
                <div id="post-identity-row" class="row g-3 align-items-end composer-identity-row">
                    <div class="col">
                        <label class="field compact-field mb-0">
                            <input type="text" id="post-author-name" name="author_name" maxlength="120" placeholder="Digite seu nome">
                        </label>
                    </div>
                    <div class="col-auto">
                        <div class="field google-field compact-field mb-0">
                            <div class="google-login-slot">
                                <div class="google-button js-google-btn"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field tribute-composer-field mb-0">
                    <span class="composer-field-label">Escreva sua mensagem de homenagem ou condolencia</span>
                    <div class="editor-shell quill-shell">
                        <div id="quill-toolbar" class="editor-toolbar quill-toolbar">
                            <span class="ql-formats">
                                <button class="ql-bold" type="button" title="Negrito"></button>
                                <button class="ql-italic" type="button" title="Italico"></button>
                                <button class="ql-list" type="button" value="bullet" title="Lista"></button>
                            </span>
                            <div class="emoji-group quill-emoji-group" aria-label="Emocoes de condolencias">
                                <button class="toolbar-button" type="button" data-quill-emoji="&#10014;" title="Cruz">&#10014;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#128591;" title="Oracao">&#128591;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#128367;&#65039;" title="Vela">&#128367;&#65039;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#127801;" title="Flor">&#127801;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#129704;" title="Flor murcha">&#129704;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#129293;" title="Coracao branco">&#129293;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#10084;&#65039;" title="Coracao vermelho">&#10084;&#65039;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#128420;" title="Coracao preto">&#128420;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#129730;" title="Abraco">&#129730;</button>
                                <button class="toolbar-button" type="button" data-quill-emoji="&#128546;" title="Choro">&#128546;</button>
                            </div>
                        </div>

                        <div id="post-editor" class="rich-editor quill-editor"></div>
                        <input type="hidden" id="post-text" name="text">
                        <input type="file" id="post-image" class="visually-hidden-file-input" name="image" accept="image/*">

                        <div class="editor-footer quill-footer">
                            <div class="attachment-status-group quill-upload-status">
                                <button class="attachment-picker-button" id="post-image-trigger" type="button" title="Anexar imagem" aria-label="Anexar imagem">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Zm1.5.5v8.336l3.624-3.624a1 1 0 0 1 1.414 0l2.208 2.208 2.71-3.388a1 1 0 0 1 1.562.01L18.5 11.52V6h-13ZM18.5 18.5v-3.78l-2.26-2.938-2.62 3.275a1 1 0 0 1-1.477.089l-2.312-2.312L5.5 17.164V18.5h13ZM8.75 10.25a1.75 1.75 0 1 0 0-3.5 1.75 1.75 0 0 0 0 3.5Z"/>
                                    </svg>
                                </button>
                                <div id="selected-file-name" class="attachment-status field-help">
                                    <span id="attachment-status-text">Nenhum anexo</span>
                                </div>
                            </div>
                            <span class="field-help quill-upload-help">Imagem opcional, ate 2 MB</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions composer-submit-row">
                    <button id="post-submit-button" class="primary-button" type="submit">Enviar</button>
                </div>
            </div>
        </form>
    </section>

    <section class="feed-section">
        <div class="section-divider"></div>
        <div id="feed-list" class="feed-list"></div>
        <div id="empty-feed" class="empty-state">Nenhuma postagem ainda. Publique a primeira.</div>
    </section>
</main>

<div id="image-lightbox" class="image-lightbox is-hidden" aria-hidden="true">
    <button id="lightbox-close" class="lightbox-close" type="button" aria-label="Fechar imagem">&times;</button>
    <img id="lightbox-image" class="lightbox-image" src="" alt="Imagem ampliada">
</div>

<script>
    window.APP_CONFIG = {
        apiBase: <?= json_encode(appUrl('api'), JSON_UNESCAPED_SLASHES) ?>,
        appBase: <?= json_encode(appUrl(), JSON_UNESCAPED_SLASHES) ?>,
        googleClientId: <?= json_encode($googleClientId, JSON_UNESCAPED_SLASHES) ?>,
        googleEnabled: <?= json_encode($googleEnabled) ?>,
        memorialKey: <?= json_encode($memorialKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        currentUser: <?= json_encode($currentUser, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<?php if ($googleEnabled): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js" defer></script>
<script src="./assets/js/app.js" defer></script>
</body>
</html>

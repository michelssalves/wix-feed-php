<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Support/helpers.php';
require_once __DIR__ . '/src/Support/session.php';

try {
    $config = config();
    date_default_timezone_set($config['timezone'] ?? 'UTC');
    startAppSession($config);
    $currentUser = currentUser();
    $googleClientId = trim((string) ($config['google']['client_id'] ?? ''));
    $googleEnabled = isGoogleConfigured();
    $memorialKey = requestMemorialKey();
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
    <link rel="stylesheet" href="./assets/css/styles.css">
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
            <div id="post-identity-row" class="field-row">
                <label class="field compact-field">
                    <input type="text" id="post-author-name" name="author_name" maxlength="120" placeholder="Digite seu nome">
                </label>
                <div class="field google-field compact-field">
                    <div class="google-login-slot">
                        <div class="google-button js-google-btn"></div>
                    </div>
                </div>
            </div>

            <div class="field">
                <span>Escreva sua mensagem de homenagem ou condolencia</span>
                <div class="editor-shell">
                    <div class="editor-toolbar">
                        <button class="toolbar-button" type="button" data-editor-command="bold" title="Negrito"><strong>B</strong></button>
                        <button class="toolbar-button" type="button" data-editor-command="italic" title="Italico"><em>I</em></button>
                        <button class="toolbar-button" type="button" data-editor-command="insertUnorderedList" title="Lista">&bull;</button>
                        <div class="emoji-group" aria-label="Emocoes de condolencias">
                            <button class="toolbar-button" type="button" data-editor-emoji="✞" title="Cruz">✞</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="🙏" title="Oracao">🙏</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="🕯️" title="Vela">🕯️</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="🌹" title="Flor">🌹</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="🥀" title="Flor murcha">🥀</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="🤍" title="Coracao branco">🤍</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="❤️" title="Coracao vermelho">❤️</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="🖤" title="Coracao preto">🖤</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="🫂" title="Abraco">🫂</button>
                            <button class="toolbar-button" type="button" data-editor-emoji="😢" title="Choro">😢</button>
                        </div>
                    </div>
                    <div id="post-editor" class="rich-editor" contenteditable="true" data-placeholder="Escreva aqui"></div>
                    <input type="hidden" id="post-text" name="text">
                    <input type="file" id="post-image" name="image" accept="image/*" hidden>
                    <div class="editor-footer">
                        <div class="attachment-status-group">
                            <button class="attachment-picker-button" id="post-image-trigger" type="button" title="Anexar imagem" aria-label="Anexar imagem">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Zm1.5.5v8.336l3.624-3.624a1 1 0 0 1 1.414 0l2.208 2.208 2.71-3.388a1 1 0 0 1 1.562.01L18.5 11.52V6h-13ZM18.5 18.5v-3.78l-2.26-2.938-2.62 3.275a1 1 0 0 1-1.477.089l-2.312-2.312L5.5 17.164V18.5h13ZM8.75 10.25a1.75 1.75 0 1 0 0-3.5 1.75 1.75 0 0 0 0 3.5Z"/>
                                </svg>
                            </button>
                            <div id="selected-file-name" class="attachment-status field-help">
                                <span id="attachment-status-text">Nenhum anexo</span>
                            </div>
                        </div>
                        <span class="field-help">Imagem opcional, ate 2 MB</span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button class="primary-button" type="submit">Enviar</button>
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
<script src="./assets/js/app.js" defer></script>
</body>
</html>

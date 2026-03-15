<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\MemorialService;
use App\Services\UploadService;

require_once __DIR__ . '/src/Support/helpers.php';
require_once __DIR__ . '/src/Support/session.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

try {
    $config = config();
    date_default_timezone_set($config['timezone'] ?? 'UTC');
    startAppSession($config);
    $pdo = Database::connection($config);
    $memorialService = new MemorialService($pdo);
    $uploadService = new UploadService($config['upload']);
    $perPage = 10;
    $page = max(1, (int) ($_GET['page'] ?? 1));

    $created = null;
    $error = '';
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? 'create');

        if ($action === 'delete') {
            $memorialId = (int) ($_POST['memorial_id'] ?? 0);

            if ($memorialId <= 0) {
                throw new RuntimeException('Memorial invalido para exclusao.');
            }

            if (!$memorialService->deleteById($memorialId)) {
                throw new RuntimeException('Memorial nao encontrado para exclusao.');
            }

            $success = 'Memorial excluido com sucesso.';
        } else {
            $deceasedName = trim((string) ($_POST['nome_falecido'] ?? ''));
            $photoPath = $uploadService->uploadImage($_FILES['foto_falecido'] ?? []);
            $created = $memorialService->create(mb_substr($deceasedName, 0, 160), $photoPath);
        }
    }

    $total = $memorialService->count();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $memorials = $memorialService->latest($perPage, $offset);
} catch (Throwable $throwable) {
    http_response_code(500);
    $error = $throwable->getMessage();
    $memorials = [];
    $created = null;
    $totalPages = 1;
    $page = 1;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gerar Memorial</title>
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body>
<main class="app-shell">
    <section class="composer">
        <div class="section-divider"></div>
        <p style="margin:0 0 8px;color:#e3c56e;letter-spacing:.12em;text-transform:uppercase;font-size:12px">Painel</p>
        <h1 style="margin:0 0 10px;font-size:clamp(28px,4vw,40px)">Gerar Memorial Key</h1>
        <p class="field-help" style="margin:0 0 18px;max-width:760px">Crie uma chave unica para o mural. O nome do memorial e opcional e serve apenas para facilitar sua identificacao interna.</p>

        <?php if ($error !== ''): ?>
            <div class="flash-message is-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="flash-message is-success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($created): ?>
            <div class="flash-message is-success">
                Key criada com sucesso:
                <strong><?= e($created['memorial_key']) ?></strong><br>
                URL:
                <a href="<?= e(appUrl('?memorial_key=' . $created['memorial_key'])) ?>" style="color:#f4f0e7">
                    <?= e(appUrl('?memorial_key=' . $created['memorial_key'])) ?>
                </a>
                <button class="copy-button" type="button" data-copy-text="<?= e(appUrl('?memorial_key=' . $created['memorial_key'])) ?>">Copy</button>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <label class="field">
                <span>Nome do memorial</span>
                <input type="text" name="nome_falecido" maxlength="160" placeholder="Opcional">
            </label>
            <label class="field">
                <span>Foto da pessoa</span>
                <input type="file" name="foto_falecido" accept="image/*">
                <small class="field-help">Opcional. Aceita apenas imagem com ate 2 MB.</small>
            </label>
            <div class="form-actions">
                <button class="primary-button" type="submit">Gerar key</button>
            </div>
        </form>
    </section>

    <section class="feed-section">
        <div class="section-divider"></div>
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
            <div>
                <h2 style="margin:0">Memoriais criados</h2>
                <p class="field-help" style="margin:6px 0 0">Lista paginada das chaves ja geradas.</p>
            </div>
            <div class="field-help">Pagina <?= (int) $page ?> de <?= (int) $totalPages ?></div>
        </div>
        <div class="feed-list">
            <?php foreach ($memorials as $memorial): ?>
                <?php $memorialInitial = mb_strtoupper(mb_substr(trim((string) ($memorial['nome_falecido'] ?: 'M')), 0, 1)); ?>
                <article class="post-card">
                    <div class="post-header">
                        <div class="avatar <?= empty($memorial['foto_falecido']) ? 'avatar--fallback' : '' ?>">
                            <?php if (!empty($memorial['foto_falecido'])): ?>
                                <img src="<?= e(appUrl($memorial['foto_falecido'])) ?>" alt="<?= e($memorial['nome_falecido'] !== '' ? $memorial['nome_falecido'] : 'Memorial') ?>">
                            <?php endif; ?>
                            <span class="avatar-fallback"><?= e($memorialInitial) ?></span>
                        </div>
                        <div class="post-header-copy">
                            <div class="author-meta">
                                <strong><?= e($memorial['nome_falecido'] !== '' ? $memorial['nome_falecido'] : 'Memorial sem nome') ?></strong>
                            </div>
                            <span class="post-date"><?= e(formatDateTimeBr($memorial['criado_em'])) ?></span>
                        </div>
                    </div>
                    <div class="post-body">
                        <div class="post-rich-text">
                            <p>
                                <strong>URL:</strong> <?= e(appUrl('?memorial_key=' . $memorial['memorial_key'])) ?>
                                <button class="copy-button" type="button" data-copy-text="<?= e(appUrl('?memorial_key=' . $memorial['memorial_key'])) ?>">Copy</button>
                            </p>
                        </div>
                        <form method="post" class="form-actions" style="justify-content:flex-start;margin-top:14px" onsubmit="return window.confirm('Deseja excluir este memorial? Isso tambem removera as postagens e comentarios vinculados.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="memorial_id" value="<?= (int) $memorial['id'] ?>">
                            <button class="secondary-button" type="submit">Excluir memorial</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="form-actions" style="justify-content:space-between;margin-top:18px">
                <div>
                    <?php if ($page > 1): ?>
                        <a class="secondary-button" href="?page=<?= $page - 1 ?>">Pagina anterior</a>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($page < $totalPages): ?>
                        <a class="secondary-button" href="?page=<?= $page + 1 ?>">Proxima pagina</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>
<script>
document.querySelectorAll('[data-copy-text]').forEach((button) => {
    button.addEventListener('click', async () => {
        const text = button.getAttribute('data-copy-text') || '';
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            const oldText = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => {
                button.textContent = oldText;
            }, 1200);
        } catch (error) {
            button.textContent = 'Error';
            setTimeout(() => {
                button.textContent = 'Copy';
            }, 1200);
        }
    });
});
</script>
</body>
</html>

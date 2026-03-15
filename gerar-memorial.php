<?php

declare(strict_types=1);

use App\Services\Database;
use App\Services\MemorialService;
use App\Services\ThemeService;
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
    $themeService = new ThemeService($pdo);
    $uploadService = new UploadService($config['upload']);
    $perPage = 10;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $defaultTheme = defaultThemeConfig();

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
        } elseif ($action === 'update-theme') {
            $themeId = (int) ($_POST['theme_id'] ?? 0);

            if ($themeId <= 0) {
                throw new RuntimeException('Tema invalido para edicao.');
            }

            $existingTheme = $themeService->findById($themeId);
            if (!$existingTheme) {
                throw new RuntimeException('Tema nao encontrado para edicao.');
            }

            $themePayload = themePayloadFromRequest($_POST, $existingTheme);
            if ($themePayload['nome'] === '') {
                throw new RuntimeException('Informe um nome para o tema.');
            }

            $themeService->update($themeId, $themePayload);
            $success = 'Tema atualizado com sucesso.';
        } else {
            $deceasedName = trim((string) ($_POST['nome_falecido'] ?? ''));
            $photoPath = $uploadService->uploadImage($_FILES['foto_falecido'] ?? []);
            $selectedThemeId = (int) ($_POST['theme_id'] ?? 0);
            $newThemeName = trim((string) ($_POST['new_theme_name'] ?? ''));
            $themeId = $selectedThemeId > 0 ? $selectedThemeId : null;

            if ($newThemeName !== '') {
                $themePayload = themePayloadFromRequest([
                    'nome' => $newThemeName,
                    'cor_fundo_pagina' => $_POST['new_cor_fundo_pagina'] ?? null,
                    'cor_fundo_formulario' => $_POST['new_cor_fundo_formulario'] ?? null,
                    'cor_fontes_principais' => $_POST['new_cor_fontes_principais'] ?? null,
                    'cor_bordas' => $_POST['new_cor_bordas'] ?? null,
                    'cor_botao_enviar' => $_POST['new_cor_botao_enviar'] ?? null,
                ], $defaultTheme);
                $themeId = $themeService->create($themePayload);
            }

            $created = $memorialService->create(mb_substr($deceasedName, 0, 160), $photoPath, $themeId);
        }
    }

    $themes = $themeService->all();
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
            <label class="field">
                <span>Tema existente</span>
                <select name="theme_id">
                    <option value="">Usar tema padrao do sistema</option>
                    <?php foreach ($themes as $theme): ?>
                        <option value="<?= (int) $theme['id'] ?>"><?= e($theme['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="field-help">Selecione um tema ja cadastrado ou crie um novo logo abaixo.</small>
            </label>

            <div class="post-card" style="margin-bottom:22px">
                <div class="post-header-copy">
                    <div class="author-meta"><strong>Novo tema reutilizavel</strong></div>
                    <span class="post-date">Se preencher um nome aqui, o tema sera salvo para uso futuro.</span>
                </div>
                <div class="field" style="margin-top:16px">
                    <span>Nome do tema</span>
                    <input type="text" name="new_theme_name" maxlength="120" placeholder="Ex.: Funeraria Central">
                </div>
                <div class="theme-grid">
                    <label class="field">
                        <span>Cor de fundo da pagina</span>
                        <input type="color" name="new_cor_fundo_pagina" value="<?= e($defaultTheme['cor_fundo_pagina']) ?>">
                    </label>
                    <label class="field">
                        <span>Cor de fundo do formulario</span>
                        <input type="color" name="new_cor_fundo_formulario" value="<?= e($defaultTheme['cor_fundo_formulario']) ?>">
                    </label>
                    <label class="field">
                        <span>Cor das fontes principais</span>
                        <input type="color" name="new_cor_fontes_principais" value="<?= e($defaultTheme['cor_fontes_principais']) ?>">
                    </label>
                    <label class="field">
                        <span>Cor das bordas</span>
                        <input type="color" name="new_cor_bordas" value="<?= e($defaultTheme['cor_bordas']) ?>">
                    </label>
                    <label class="field">
                        <span>Cor do botao Enviar</span>
                        <input type="color" name="new_cor_botao_enviar" value="<?= e($defaultTheme['cor_botao_enviar']) ?>">
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button class="primary-button" type="submit">Gerar key</button>
            </div>
        </form>
    </section>

    <section class="feed-section">
        <div class="section-divider"></div>
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
            <div>
                <h2 style="margin:0">Temas cadastrados</h2>
                <p class="field-help" style="margin:6px 0 0">Edite seus temas para reutilizar em novos memoriais.</p>
            </div>
            <div class="field-help"><?= count($themes) ?> tema(s)</div>
        </div>
        <div class="feed-list">
            <?php foreach ($themes as $theme): ?>
                <article class="post-card">
                    <form method="post">
                        <input type="hidden" name="action" value="update-theme">
                        <input type="hidden" name="theme_id" value="<?= (int) $theme['id'] ?>">
                        <div class="post-header-copy" style="margin-bottom:16px">
                            <div class="author-meta"><strong><?= e($theme['nome']) ?></strong></div>
                            <span class="post-date">Atualizado em <?= e(formatDateTimeBr($theme['atualizado_em'] ?? $theme['criado_em'])) ?></span>
                        </div>
                        <label class="field">
                            <span>Nome do tema</span>
                            <input type="text" name="nome" maxlength="120" value="<?= e($theme['nome']) ?>">
                        </label>
                        <div class="theme-grid">
                            <label class="field">
                                <span>Cor de fundo da pagina</span>
                                <input type="color" name="cor_fundo_pagina" value="<?= e($theme['cor_fundo_pagina']) ?>">
                            </label>
                            <label class="field">
                                <span>Cor de fundo do formulario</span>
                                <input type="color" name="cor_fundo_formulario" value="<?= e($theme['cor_fundo_formulario']) ?>">
                            </label>
                            <label class="field">
                                <span>Cor das fontes principais</span>
                                <input type="color" name="cor_fontes_principais" value="<?= e($theme['cor_fontes_principais']) ?>">
                            </label>
                            <label class="field">
                                <span>Cor das bordas</span>
                                <input type="color" name="cor_bordas" value="<?= e($theme['cor_bordas']) ?>">
                            </label>
                            <label class="field">
                                <span>Cor do botao Enviar</span>
                                <input type="color" name="cor_botao_enviar" value="<?= e($theme['cor_botao_enviar']) ?>">
                            </label>
                        </div>
                        <div class="form-actions">
                            <button class="primary-button" type="submit">Salvar tema</button>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
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
                <article class="post-card memorial-card">
                    <div class="post-header memorial-card__header">
                        <div class="avatar <?= empty($memorial['foto_falecido']) ? 'avatar--fallback' : '' ?>">
                            <?php if (!empty($memorial['foto_falecido'])): ?>
                                <img src="<?= e(appUrl($memorial['foto_falecido'])) ?>" alt="<?= e($memorial['nome_falecido'] !== '' ? $memorial['nome_falecido'] : 'Memorial') ?>">
                            <?php endif; ?>
                            <span class="avatar-fallback"><?= e($memorialInitial) ?></span>
                        </div>
                        <div class="post-header-copy memorial-card__identity">
                            <div class="author-meta">
                                <strong><?= e($memorial['nome_falecido'] !== '' ? $memorial['nome_falecido'] : 'Memorial sem nome') ?></strong>
                            </div>
                            <span class="post-date"><?= e(formatDateTimeBr($memorial['criado_em'])) ?><?= !empty($memorial['theme_nome']) ? ' · Tema: ' . e($memorial['theme_nome']) : '' ?></span>
                        </div>
                    </div>
                    <div class="post-body memorial-card__body">
                        <div class="post-rich-text memorial-card__content">
                            <div class="memorial-link-box">
                                <span class="memorial-link-box__label">URL do memorial</span>
                                <div class="memorial-link-box__row">
                                    <span class="memorial-link-box__value"><?= e(appUrl('?memorial_key=' . $memorial['memorial_key'])) ?></span>
                                    <button class="copy-button memorial-copy-button" type="button" data-copy-text="<?= e(appUrl('?memorial_key=' . $memorial['memorial_key'])) ?>">Copy</button>
                                </div>
                            </div>
                        </div>
                        <form method="post" class="form-actions memorial-card__actions" onsubmit="return window.confirm('Deseja excluir este memorial? Isso tambem removera as postagens e comentarios vinculados.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="memorial_id" value="<?= (int) $memorial['id'] ?>">
                            <button class="secondary-button memorial-delete-button" type="submit">Excluir memorial</button>
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

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

function memorialPanelUrl(array $params = []): string
{
    $query = array_filter($params, static fn(mixed $value): bool => $value !== null && $value !== '');
    $url = appUrl('gerar-memorial.php');

    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    return $url . '#memorial-panel';
}

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
    $activeTab = (string) ($_GET['tab'] ?? 'memoriais');
    $searchName = trim((string) ($_GET['search_nome'] ?? ''));
    $searchMemorialKey = trim((string) ($_GET['search_memorial_key'] ?? ''));
    if (!in_array($activeTab, ['memoriais', 'temas'], true)) {
        $activeTab = 'memoriais';
    }

    $created = getFlashValue('created_memorial');
    $error = (string) getFlashValue('error_message', '');
    $success = (string) getFlashValue('success_message', '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? 'create');
        $redirectParams = [
            'tab' => (string) ($_POST['redirect_tab'] ?? $activeTab),
            'page' => (int) ($_POST['redirect_page'] ?? $page) > 1 ? (int) ($_POST['redirect_page'] ?? $page) : null,
            'search_nome' => trim((string) ($_POST['redirect_search_nome'] ?? $searchName)),
            'search_memorial_key' => trim((string) ($_POST['redirect_search_memorial_key'] ?? $searchMemorialKey)),
        ];

        try {
            if ($action === 'delete') {
                $activeTab = 'memoriais';
                $memorialId = (int) ($_POST['memorial_id'] ?? 0);

                if ($memorialId <= 0) {
                    throw new RuntimeException('Memorial invalido para exclusao.');
                }

                if (!$memorialService->deleteById($memorialId)) {
                    throw new RuntimeException('Memorial nao encontrado para exclusao.');
                }

                setFlashValue('success_message', 'Memorial excluido com sucesso.');
            } elseif ($action === 'update-memorial') {
                $activeTab = 'memoriais';
                $memorialId = (int) ($_POST['memorial_id'] ?? 0);

                if ($memorialId <= 0) {
                    throw new RuntimeException('Memorial invalido para edicao.');
                }

                $existingMemorial = $memorialService->findById($memorialId);
                if (!$existingMemorial) {
                    throw new RuntimeException('Memorial nao encontrado para edicao.');
                }

                $deceasedName = trim((string) ($_POST['nome_falecido'] ?? ''));
                $photoPath = $uploadService->uploadImage($_FILES['foto_falecido'] ?? []);
                $themeId = (int) ($_POST['theme_id'] ?? 0);
                $themeId = $themeId > 0 ? $themeId : null;

                $memorialService->updateById($memorialId, mb_substr($deceasedName, 0, 160), $photoPath, $themeId);
                setFlashValue('success_message', 'Memorial atualizado com sucesso.');
            } elseif ($action === 'update-theme') {
                $activeTab = 'temas';
                $themeId = (int) ($_POST['theme_id'] ?? 0);
                $redirectParams['tab'] = 'temas';

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
                setFlashValue('success_message', 'Tema atualizado com sucesso.');
            } else {
                $activeTab = 'memoriais';
                $redirectParams = ['tab' => 'memoriais'];
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
                        'cor_texto_botao_enviar' => $_POST['new_cor_texto_botao_enviar'] ?? null,
                    ], $defaultTheme);
                    $themeId = $themeService->create($themePayload);
                }

                $created = $memorialService->create(mb_substr($deceasedName, 0, 160), $photoPath, $themeId);
                setFlashValue('created_memorial', $created);
            }
        } catch (Throwable $postThrowable) {
            setFlashValue('error_message', $postThrowable->getMessage());
        }

        header('Location: ' . memorialPanelUrl($redirectParams));
        exit;
    }

    $themes = $themeService->all();
    $memorialFilters = [
        'nome_falecido' => $searchName,
        'memorial_key' => $searchMemorialKey,
    ];
    $total = $memorialService->count($memorialFilters);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $resultStart = $total > 0 ? $offset + 1 : 0;
    $resultEnd = min($offset + $perPage, $total);
    $memorials = $memorialService->latest($perPage, $offset, $memorialFilters);
} catch (Throwable $throwable) {
    http_response_code(500);
    $error = $throwable->getMessage();
    $memorials = [];
    $themes = [];
    $created = null;
    $totalPages = 1;
    $page = 1;
    $activeTab = 'memoriais';
    $defaultTheme = defaultThemeConfig();
    $searchName = '';
    $searchMemorialKey = '';
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
                    <div class="created-links">
                        <div class="created-links__row">
                            <span class="created-links__label">Mural:</span>
                            <a href="<?= e(appUrl('?memorial_key=' . $created['memorial_key'])) ?>" style="color:#f4f0e7">
                                <?= e(appUrl('?memorial_key=' . $created['memorial_key'])) ?>
                            </a>
                            <button class="copy-button copy-icon-button" type="button" data-copy-text="<?= e(appUrl('?memorial_key=' . $created['memorial_key'])) ?>" aria-label="Copiar URL do memorial" title="Copiar URL do memorial">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1Zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 16H10V7h9v14Z" />
                                </svg>
                            </button>
                        </div>
                        <div class="created-links__row">
                            <span class="created-links__label">TV:</span>
                            <a href="<?= e(appUrl('tv-memorial.php?memorial_key=' . $created['memorial_key'])) ?>" style="color:#f4f0e7">
                                <?= e(appUrl('tv-memorial.php?memorial_key=' . $created['memorial_key'])) ?>
                            </a>
                            <button class="copy-button copy-icon-button" type="button" data-copy-text="<?= e(appUrl('tv-memorial.php?memorial_key=' . $created['memorial_key'])) ?>" aria-label="Copiar URL da TV" title="Copiar URL da TV">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1Zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 16H10V7h9v14Z" />
                                </svg>
                            </button>
                        </div>
                        <div class="created-links__row">
                            <span class="created-links__label">Album:</span>
                            <span class="created-links__spacer" aria-hidden="true"></span>
                            <button class="copy-button copy-icon-button memorial-download-button memorial-album-button" type="button" data-download-album="<?= e($created['memorial_key']) ?>" aria-label="Baixar album" title="Baixar album">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 3a1 1 0 0 1 1 1v8.586l2.293-2.293 1.414 1.414-4.707 4.707-4.707-4.707 1.414-1.414L11 12.586V4a1 1 0 0 1 1-1Zm-7 14h14v2H5v-2Z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <details class="admin-accordion admin-accordion--composer">
                <summary class="admin-accordion__summary">
                    <span class="admin-accordion__title">Gerar Memorial Key</span>
                    <span class="admin-accordion__icon" aria-hidden="true"></span>
                </summary>
                <div class="admin-accordion__content">
                    <form method="post" enctype="multipart/form-data" class="memorial-admin-form">
                        <input type="hidden" name="action" value="create">
                        <div class="admin-section">
                            <div class="admin-section__header">
                                <div>
                                    <h2 class="admin-section__title">Dados do memorial</h2>
                                </div>
                            </div>
                            <div class="admin-grid admin-grid--memorial">
                                <label class="field">
                                    <span>Nome do memorial</span>
                                    <input type="text" name="nome_falecido" maxlength="160" placeholder="Opcional">
                                </label>
                                <label class="field field--file">
                                    <span>Foto da pessoa</span>
                                    <input type="file" name="foto_falecido" accept="image/*">
                                    <small class="field-help">Opcional. Aceita apenas imagem com ate 2 MB.</small>
                                </label>
                            </div>
                        </div>

                        <div class="admin-section">
                            <div class="admin-section__header">
                                <div>
                                    <h2 class="admin-section__title">Tema cadastrados</h2>
                                </div>
                            </div>
                            <label class="field">
                                <span>Selecione um tema</span>
                                <select name="theme_id">
                                    <option value="">Usar tema padrao do sistema</option>
                                    <?php foreach ($themes as $theme): ?>
                                        <option value="<?= (int) $theme['id'] ?>"><?= e($theme['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="admin-section admin-section--theme-builder">
                            <div class="admin-section__header">
                                <div>
                                    <h2 class="admin-section__title">Criacao de tema reutilizavel</h2>
                                </div>
                            </div>

                            <div class="admin-theme-layout">
                                <div class="admin-theme-builder">
                                    <div class="field">
                                        <span>Nome do tema</span>
                                        <input type="text" name="new_theme_name" maxlength="120" placeholder="Ex.: Funeraria Central">
                                    </div>
                                    <div class="theme-grid theme-grid--builder">
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
                                        <label class="field">
                                            <span>Cor do texto do botao</span>
                                            <input type="color" name="new_cor_texto_botao_enviar" value="<?= e($defaultTheme['cor_texto_botao_enviar']) ?>">
                                        </label>
                                    </div>
                                </div>

                                <div class="admin-theme-preview-wrap">
                                    <div class="admin-section__header admin-section__header--compact">
                                        <div>
                                            <h3 class="admin-section__subtitle">Preview ao vivo</h3>
                                            <p class="admin-section__text">Veja como o memorial vai ficar antes de salvar.</p>
                                        </div>
                                    </div>
                                    <div class="theme-preview" id="new-theme-preview" style="<?= e('background:' . $defaultTheme['cor_fundo_pagina'] . ';border-color:' . $defaultTheme['cor_bordas'] . ';color:' . $defaultTheme['cor_fontes_principais']) ?>">
                                        <div class="theme-preview__panel" style="<?= e('background:' . $defaultTheme['cor_fundo_formulario'] . ';border-color:' . $defaultTheme['cor_bordas']) ?>">
                                            <span class="theme-preview__eyebrow">Preview ao vivo</span>
                                            <strong class="theme-preview__title" id="new-theme-preview-name">Tema padrao</strong>
                                            <p class="theme-preview__text">Assim o mural sera exibido para esse memorial.</p>
                                            <button type="button" class="theme-preview__button" id="new-theme-preview-button" style="<?= e('background:' . $defaultTheme['cor_botao_enviar'] . ';color:' . $defaultTheme['cor_texto_botao_enviar']) ?>">Enviar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="admin-submit-bar">
                            <div class="admin-submit-bar__copy">
                                <strong>Gerar memorial</strong>
                                <span>Crie a key final com os dados e tema definidos acima.</span>
                            </div>
                            <div class="form-actions">
                                <button class="primary-button primary-button--large" type="submit">Gerar key</button>
                            </div>
                        </div>
                    </form>
                </div>
            </details>
        </section>

        <section class="feed-section" id="memorial-panel">
            <div class="section-divider"></div>
            <?php
            $memorialTabQuery = ['tab' => 'memoriais'];
            if ($page > 1) {
                $memorialTabQuery['page'] = $page;
            }
            if ($searchName !== '') {
                $memorialTabQuery['search_nome'] = $searchName;
            }
            if ($searchMemorialKey !== '') {
                $memorialTabQuery['search_memorial_key'] = $searchMemorialKey;
            }

            $themeTabQuery = ['tab' => 'temas'];
            if ($searchName !== '') {
                $themeTabQuery['search_nome'] = $searchName;
            }
            if ($searchMemorialKey !== '') {
                $themeTabQuery['search_memorial_key'] = $searchMemorialKey;
            }
            ?>
            <div class="panel-tabs" role="tablist" aria-label="Navegacao de memoriais e temas">
                <a class="panel-tab<?= $activeTab === 'memoriais' ? ' is-active' : '' ?>" href="?<?= e(http_build_query($memorialTabQuery)) ?>#memorial-panel">Memoriais cadastrados <span class="panel-tab-count"><?= $total ?></span></a>
                <a class="panel-tab<?= $activeTab === 'temas' ? ' is-active' : '' ?>" href="?<?= e(http_build_query($themeTabQuery)) ?>#memorial-panel">Temas cadastrados <span class="panel-tab-count"><?= count($themes) ?></span></a>
            </div>

            <?php if ($activeTab === 'temas'): ?>
                <div class="panel-section-header panel-section-header--themes">
                    <div>
                        <h2 class="panel-section-header__eyebrow">Biblioteca visual</h2>
                    </div>
                    <div class="panel-section-header__badge"><?= count($themes) ?> tema(s)</div>
                </div>
                <div class="feed-list">
                    <?php foreach ($themes as $theme): ?>
                        <article class="post-card theme-manager-card">
                            <details class="theme-manager-accordion">
                                <summary class="theme-manager-accordion__summary">
                                    <div class="theme-manager-card__header-main">
                                        <p class="theme-manager-card__eyebrow">Tema reutilizavel</p>
                                        <h3 class="theme-manager-card__title"><?= e($theme['nome']) ?></h3>
                                        <span class="theme-manager-card__date">Atualizado em <?= e(formatDateTimeBr($theme['atualizado_em'] ?? $theme['criado_em'])) ?></span>
                                    </div>
                                    <div class="theme-manager-accordion__summary-side">
                                        <div class="theme-swatches theme-swatches--compact" aria-label="Previa das cores do tema">
                                            <span class="theme-swatch">
                                                <span class="theme-swatch__color" style="background:<?= e($theme['cor_fundo_pagina']) ?>"></span>
                                                <span class="theme-swatch__label">Pagina</span>
                                            </span>
                                            <span class="theme-swatch">
                                                <span class="theme-swatch__color" style="background:<?= e($theme['cor_fundo_formulario']) ?>"></span>
                                                <span class="theme-swatch__label">Formulario</span>
                                            </span>
                                            <span class="theme-swatch">
                                                <span class="theme-swatch__color" style="background:<?= e($theme['cor_fontes_principais']) ?>"></span>
                                                <span class="theme-swatch__label">Fonte</span>
                                            </span>
                                            <span class="theme-swatch">
                                                <span class="theme-swatch__color" style="background:<?= e($theme['cor_bordas']) ?>"></span>
                                                <span class="theme-swatch__label">Borda</span>
                                            </span>
                                            <span class="theme-swatch">
                                                <span class="theme-swatch__color" style="background:<?= e($theme['cor_botao_enviar']) ?>"></span>
                                                <span class="theme-swatch__label">Botao</span>
                                            </span>
                                            <span class="theme-swatch">
                                                <span class="theme-swatch__color" style="background:<?= e($theme['cor_texto_botao_enviar']) ?>"></span>
                                                <span class="theme-swatch__label">Texto botao</span>
                                            </span>
                                        </div>
                                        <span class="theme-manager-accordion__icon" aria-hidden="true"></span>
                                    </div>
                                </summary>

                                <form method="post" class="theme-manager-form">
                                    <input type="hidden" name="action" value="update-theme">
                                    <input type="hidden" name="theme_id" value="<?= (int) $theme['id'] ?>">
                                    <input type="hidden" name="redirect_tab" value="temas">

                                    <div class="theme-manager-layout">
                                        <div class="theme-manager-main">
                                            <label class="field">
                                                <span>Nome do tema</span>
                                                <input type="text" name="nome" maxlength="120" value="<?= e($theme['nome']) ?>">
                                            </label>

                                            <div class="theme-grid theme-grid--theme-manager">
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
                                                <label class="field">
                                                    <span>Cor do texto do botao</span>
                                                    <input type="color" name="cor_texto_botao_enviar" value="<?= e($theme['cor_texto_botao_enviar']) ?>">
                                                </label>
                                            </div>
                                        </div>

                                        <aside class="theme-manager-side">
                                            <div class="theme-preview theme-preview--small theme-preview--theme-card" style="<?= e('background:' . $theme['cor_fundo_pagina'] . ';border-color:' . $theme['cor_bordas'] . ';color:' . $theme['cor_fontes_principais']) ?>">
                                                <div class="theme-preview__panel" style="<?= e('background:' . $theme['cor_fundo_formulario'] . ';border-color:' . $theme['cor_bordas']) ?>">
                                                    <span class="theme-preview__eyebrow">Preview aplicado</span>
                                                    <span class="theme-preview__title"><?= e($theme['nome']) ?></span>
                                                    <p class="theme-preview__text">Visual resumido do mural usando essa combinacao de cores.</p>
                                                    <button type="button" class="theme-preview__button" style="<?= e('background:' . $theme['cor_botao_enviar'] . ';color:' . $theme['cor_texto_botao_enviar']) ?>">Enviar</button>
                                                </div>
                                            </div>
                                        </aside>
                                    </div>

                                    <div class="theme-manager-actions">
                                        <button class="primary-button primary-button--large" type="submit">Salvar tema</button>
                                    </div>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
                    <div>
                        <h2 style="margin:0">Memoriais criados</h2>
                        <p class="field-help" style="margin:6px 0 0">Lista paginada das chaves ja geradas.</p>
                    </div>
                    <div class="field-help">Pagina <?= (int) $page ?> de <?= (int) $totalPages ?></div>
                </div>
                <div class="memorial-filter-bar">
                    <form method="get" class="memorial-filter-form">
                        <input type="hidden" name="tab" value="memoriais">
                        <label class="field">
                            <span>Nome</span>
                            <input type="text" name="search_nome" value="<?= e($searchName) ?>" placeholder="Ex.: Maria Silva">
                        </label>
                        <label class="field">
                            <span>Memorial Key</span>
                            <input type="text" name="search_memorial_key" value="<?= e($searchMemorialKey) ?>" placeholder="Ex.: 13258757">
                        </label>
                        <div class="memorial-filter-form__actions">
                            <button class="copy-button copy-icon-button memorial-filter-icon-button" type="submit" aria-label="Filtrar memoriais" title="Filtrar memoriais">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M10 4a6 6 0 1 0 3.874 10.582l4.272 4.272 1.414-1.414-4.272-4.272A6 6 0 0 0 10 4Zm0 2a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z" />
                                </svg>
                            </button>
                            <a class="copy-button copy-icon-button memorial-filter-icon-button" href="?tab=memoriais#memorial-panel" aria-label="Limpar filtros" title="Limpar filtros">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M6.225 4.811 4.811 6.225 10.586 12l-5.775 5.775 1.414 1.414L12 13.414l5.775 5.775 1.414-1.414L13.414 12l5.775-5.775-1.414-1.414L12 10.586 6.225 4.811Z" />
                                </svg>
                            </a>
                        </div>
                    </form>
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
                                        <span class="memorial-link-box__label">Links do memorial</span>
                                        <div class="memorial-link-box__row">
                                            <span class="memorial-link-box__meta">Mural</span>
                                            <a class="memorial-link-box__value" href="<?= e(appUrl('?memorial_key=' . $memorial['memorial_key'])) ?>" target="_blank" rel="noopener noreferrer"><?= e(appUrl('?memorial_key=' . $memorial['memorial_key'])) ?></a>
                                            <button class="copy-button copy-icon-button memorial-copy-button" type="button" data-copy-text="<?= e(appUrl('?memorial_key=' . $memorial['memorial_key'])) ?>" aria-label="Copiar URL do memorial" title="Copiar URL do memorial">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1Zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 16H10V7h9v14Z" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="memorial-link-box__row">
                                            <span class="memorial-link-box__meta">TV</span>
                                            <a class="memorial-link-box__value" href="<?= e(appUrl('tv-memorial.php?memorial_key=' . $memorial['memorial_key'])) ?>" target="_blank" rel="noopener noreferrer"><?= e(appUrl('tv-memorial.php?memorial_key=' . $memorial['memorial_key'])) ?></a>
                                            <button class="copy-button copy-icon-button memorial-copy-button" type="button" data-copy-text="<?= e(appUrl('tv-memorial.php?memorial_key=' . $memorial['memorial_key'])) ?>" aria-label="Copiar URL da TV" title="Copiar URL da TV">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1Zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 16H10V7h9v14Z" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="memorial-link-box__row memorial-link-box__row--action">
                                            <span class="memorial-link-box__meta">Album</span>
                                            <span class="memorial-link-box__value memorial-link-box__value--empty" aria-hidden="true"></span>
                                            <button class="copy-button copy-icon-button memorial-download-button memorial-album-button" type="button" data-download-album="<?= e($memorial['memorial_key']) ?>" aria-label="Baixar album" title="Baixar album">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M12 3a1 1 0 0 1 1 1v8.586l2.293-2.293 1.414 1.414-4.707 4.707-4.707-4.707 1.414-1.414L11 12.586V4a1 1 0 0 1 1-1Zm-7 14h14v2H5v-2Z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <details class="memorial-edit-panel">
                                    <summary class="secondary-button memorial-edit-toggle">Editar memorial</summary>
                                    <form method="post" enctype="multipart/form-data" class="memorial-edit-form">
                                        <input type="hidden" name="action" value="update-memorial">
                                        <input type="hidden" name="memorial_id" value="<?= (int) $memorial['id'] ?>">
                                        <input type="hidden" name="redirect_tab" value="memoriais">
                                        <input type="hidden" name="redirect_page" value="<?= (int) $page ?>">
                                        <input type="hidden" name="redirect_search_nome" value="<?= e($searchName) ?>">
                                        <input type="hidden" name="redirect_search_memorial_key" value="<?= e($searchMemorialKey) ?>">
                                        <label class="field">
                                            <span>Nome do memorial</span>
                                            <input type="text" name="nome_falecido" maxlength="160" value="<?= e($memorial['nome_falecido']) ?>">
                                        </label>
                                        <label class="field">
                                            <span>Foto da pessoa</span>
                                            <input type="file" name="foto_falecido" accept="image/*">
                                            <small class="field-help">Envie apenas se quiser substituir a foto atual.</small>
                                        </label>
                                        <label class="field">
                                            <span>Tema do memorial</span>
                                            <select name="theme_id">
                                                <option value="">Usar tema padrao do sistema</option>
                                                <?php foreach ($themes as $theme): ?>
                                                    <option value="<?= (int) $theme['id'] ?>" <?= (int) ($memorial['theme_id'] ?? 0) === (int) $theme['id'] ? ' selected' : '' ?>><?= e($theme['nome']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <div class="form-actions memorial-card__actions">
                                            <button class="primary-button" type="submit">Salvar memorial</button>
                                        </div>
                                    </form>
                                </details>
                                <form method="post" class="form-actions memorial-card__actions memorial-card__actions--danger" onsubmit="return window.confirm('Deseja excluir este memorial? Isso tambem removera as postagens e comentarios vinculados.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="memorial_id" value="<?= (int) $memorial['id'] ?>">
                                    <input type="hidden" name="redirect_tab" value="memoriais">
                                    <input type="hidden" name="redirect_page" value="<?= (int) $page ?>">
                                    <input type="hidden" name="redirect_search_nome" value="<?= e($searchName) ?>">
                                    <input type="hidden" name="redirect_search_memorial_key" value="<?= e($searchMemorialKey) ?>">
                                    <button class="secondary-button memorial-delete-button" type="submit">Excluir memorial</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <?php
                    $paginationBase = ['tab' => 'memoriais'];
                    if ($searchName !== '') {
                        $paginationBase['search_nome'] = $searchName;
                    }
                    if ($searchMemorialKey !== '') {
                        $paginationBase['search_memorial_key'] = $searchMemorialKey;
                    }
                    ?>
                    <nav class="pagination-bar" aria-label="Paginacao dos memoriais">
                        <span class="pagination-summary">Showing <?= (int) $resultStart ?> to <?= (int) $resultEnd ?> of <?= (int) $total ?> results</span>
                        <div class="pagination-controls">
                            <a class="pagination-button<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= $page > 1 ? '?' . e(http_build_query(array_merge($paginationBase, ['page' => $page - 1]))) . '#memorial-panel' : '#' ?>" aria-label="Pagina anterior" <?= $page <= 1 ? ' tabindex="-1" aria-disabled="true"' : '' ?>>
                                <span aria-hidden="true">&lsaquo;</span>
                            </a>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a class="pagination-button<?= $i === $page ? ' is-active' : '' ?>" href="?<?= e(http_build_query(array_merge($paginationBase, ['page' => $i]))) ?>#memorial-panel" aria-current="<?= $i === $page ? 'page' : 'false' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a class="pagination-button<?= $page >= $totalPages ? ' is-disabled' : '' ?>" href="<?= $page < $totalPages ? '?' . e(http_build_query(array_merge($paginationBase, ['page' => $page + 1]))) . '#memorial-panel' : '#' ?>" aria-label="Proxima pagina" <?= $page >= $totalPages ? ' tabindex="-1" aria-disabled="true"' : '' ?>>
                                <span aria-hidden="true">&rsaquo;</span>
                            </a>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
    <script>
        const themePreviewElements = {
            wrapper: document.getElementById('new-theme-preview'),
            panel: document.querySelector('#new-theme-preview .theme-preview__panel'),
            name: document.getElementById('new-theme-preview-name'),
            button: document.getElementById('new-theme-preview-button'),
        };

        function syncThemePreview() {
            if (!themePreviewElements.wrapper || !themePreviewElements.panel || !themePreviewElements.name || !themePreviewElements.button) {
                return;
            }

            const getValue = (name, fallback) => {
                const input = document.querySelector(`[name="${name}"]`);
                return input && input.value ? input.value : fallback;
            };

            const themeNameInput = document.querySelector('[name="new_theme_name"]');
            themePreviewElements.wrapper.style.background = getValue('new_cor_fundo_pagina', '<?= e($defaultTheme['cor_fundo_pagina']) ?>');
            themePreviewElements.wrapper.style.borderColor = getValue('new_cor_bordas', '<?= e($defaultTheme['cor_bordas']) ?>');
            themePreviewElements.wrapper.style.color = getValue('new_cor_fontes_principais', '<?= e($defaultTheme['cor_fontes_principais']) ?>');
            themePreviewElements.panel.style.background = getValue('new_cor_fundo_formulario', '<?= e($defaultTheme['cor_fundo_formulario']) ?>');
            themePreviewElements.panel.style.borderColor = getValue('new_cor_bordas', '<?= e($defaultTheme['cor_bordas']) ?>');
            themePreviewElements.button.style.background = getValue('new_cor_botao_enviar', '<?= e($defaultTheme['cor_botao_enviar']) ?>');
            themePreviewElements.button.style.color = getValue('new_cor_texto_botao_enviar', '<?= e($defaultTheme['cor_texto_botao_enviar']) ?>');
            themePreviewElements.name.textContent = themeNameInput && themeNameInput.value.trim() !== '' ? themeNameInput.value.trim() : 'Tema padrao';
        }

        ['new_theme_name', 'new_cor_fundo_pagina', 'new_cor_fundo_formulario', 'new_cor_fontes_principais', 'new_cor_bordas', 'new_cor_botao_enviar', 'new_cor_texto_botao_enviar'].forEach((name) => {
            const input = document.querySelector(`[name="${name}"]`);
            if (input) {
                input.addEventListener('input', syncThemePreview);
                input.addEventListener('change', syncThemePreview);
            }
        });

        document.querySelectorAll('[data-copy-text]').forEach((button) => {
            button.addEventListener('click', async () => {
                const text = button.getAttribute('data-copy-text') || '';
                if (!text) return;
                try {
                    await navigator.clipboard.writeText(text);
                    button.classList.add('is-copied');
                    setTimeout(() => {
                        button.classList.remove('is-copied');
                    }, 1200);
                } catch (error) {
                    button.classList.add('is-copy-error');
                    setTimeout(() => {
                        button.classList.remove('is-copy-error');
                    }, 1200);
                }
            });
        });

        document.querySelectorAll('[data-download-album]').forEach((button) => {
            button.addEventListener('click', async () => {
                const memorialKey = button.getAttribute('data-download-album') || '';
                if (!memorialKey) return;

                button.disabled = true;
                button.classList.add('is-loading');

                try {
                    const response = await fetch(`<?= e(appUrl('album-memorial.php')) ?>?memorial_key=${encodeURIComponent(memorialKey)}`, {
                        credentials: 'include',
                    });

                    if (!response.ok) {
                        throw new Error('Nao foi possivel gerar o album.');
                    }

                    const blob = await response.blob();
                    const downloadUrl = URL.createObjectURL(blob);
                    const disposition = response.headers.get('Content-Disposition') || '';
                    const fileNameMatch = disposition.match(/filename="([^"]+)"/i);
                    const fileName = fileNameMatch?.[1] || `album-memorial-${memorialKey}.html`;
                    const anchor = document.createElement('a');
                    anchor.href = downloadUrl;
                    anchor.download = fileName;
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    URL.revokeObjectURL(downloadUrl);
                    button.classList.add('is-copied');
                    setTimeout(() => {
                        button.classList.remove('is-copied');
                    }, 1400);
                } catch (error) {
                    button.classList.add('is-copy-error');
                    setTimeout(() => {
                        button.classList.remove('is-copy-error');
                    }, 1600);
                } finally {
                    button.classList.remove('is-loading');
                    setTimeout(() => {
                        button.disabled = false;
                    }, 300);
                }
            });
        });

        syncThemePreview();
    </script>
</body>

</html>

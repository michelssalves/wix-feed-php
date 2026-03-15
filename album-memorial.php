<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Support/helpers.php';
require_once __DIR__ . '/src/Support/session.php';
require_once __DIR__ . '/src/Services/Database.php';
require_once __DIR__ . '/src/Services/MemorialService.php';
require_once __DIR__ . '/src/Services/FeedService.php';

use App\Services\Database;
use App\Services\FeedService;
use App\Services\MemorialService;

function albumSlug(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? 'memorial';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'memorial';
}

try {
    $config = config();
    date_default_timezone_set($config['timezone'] ?? 'UTC');
    startAppSession($config);

    $memorialKey = requestMemorialKey();
    if ($memorialKey === '') {
        throw new RuntimeException('Memorial nao informado.');
    }

    $pdo = Database::connection($config);
    $memorialService = new MemorialService($pdo);
    $feedService = new FeedService($pdo);
    $memorial = $memorialService->findByKey($memorialKey);

    if (!$memorial) {
        throw new RuntimeException('Memorial nao encontrado.');
    }

    $posts = $feedService->feed($memorialKey);
    $memorialName = trim((string) ($memorial['nome_falecido'] ?? 'Memorial'));
    $memorialName = $memorialName !== '' ? $memorialName : 'Memorial';
    $fileName = sprintf('album-%s-%s.html', albumSlug($memorialName), $memorialKey);
    $coverImage = !empty($memorial['foto_falecido']) ? appUrl($memorial['foto_falecido']) : '';

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
?>
    <!doctype html>
    <html lang="pt-BR">

    <head>
        <meta charset="utf-8">
        <title>Album Memorial | <?= e($memorialName) ?></title>
        <style>
            :root {
                --page: #f2ede5;
                --paper: #fffdf9;
                --line: #d9cfbd;
                --text: #2f271f;
                --muted: #766b5d;
                --accent: #b6934b;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                background: linear-gradient(180deg, #ece6dc 0%, #f7f3ec 100%);
                color: var(--text);
                font-family: Georgia, "Times New Roman", serif;
            }

            .album {
                width: min(1080px, calc(100% - 48px));
                margin: 28px auto;
                display: grid;
                gap: 24px;
            }

            .cover,
            .entry {
                background: var(--paper);
                border: 1px solid var(--line);
                padding: 28px;
            }

            .cover {
                display: grid;
                gap: 20px;
                text-align: center;
            }

            .cover img {
                width: min(220px, 100%);
                aspect-ratio: 1 / 1;
                object-fit: cover;
                margin: 0 auto;
                border: 1px solid var(--line);
            }

            .cover__eyebrow {
                margin: 0;
                color: var(--accent);
                letter-spacing: 0.18em;
                text-transform: uppercase;
                font-size: 12px;
                font-family: Arial, sans-serif;
                font-weight: 700;
            }

            .cover h1 {
                margin: 0;
                font-size: 42px;
                line-height: 1.05;
            }

            .cover__meta {
                margin: 0;
                color: var(--muted);
                font-family: Arial, sans-serif;
                line-height: 1.6;
            }

            .entry {
                display: grid;
                gap: 18px;
            }

            .entry--text-only {
                padding: 34px;
                background:
                    linear-gradient(180deg, rgba(182, 147, 75, 0.06) 0%, rgba(255, 253, 249, 1) 24%),
                    var(--paper);
            }

            .entry__meta {
                display: grid;
                gap: 6px;
                padding-bottom: 14px;
                border-bottom: 1px solid var(--line);
            }

            .entry__author {
                font-size: 26px;
                line-height: 1.15;
                font-weight: 700;
            }

            .entry__date {
                color: var(--muted);
                font-family: Arial, sans-serif;
                font-size: 14px;
            }

            .entry__body {
                display: grid;
                gap: 18px;
            }

            .entry--text-only .entry__body {
                gap: 22px;
            }

            .entry__text {
                font-size: 24px;
                line-height: 1.7;
            }

            .entry--text-only .entry__text {
                position: relative;
                padding: 6px 0 0 28px;
                font-size: clamp(28px, 3.4vw, 42px);
                line-height: 1.55;
            }

            .entry--text-only .entry__text::before {
                content: "“";
                position: absolute;
                left: 0;
                top: -8px;
                color: var(--accent);
                font-size: 58px;
                line-height: 1;
            }

            .entry__text p,
            .entry__text ul,
            .entry__text ol {
                margin: 0 0 14px;
            }

            .entry__text p:last-child,
            .entry__text ul:last-child,
            .entry__text ol:last-child {
                margin-bottom: 0;
            }

            .entry__image {
                width: 100%;
                border: 1px solid var(--line);
                background: #f6f1e7;
                padding: 12px;
            }

            .entry__image img {
                width: 100%;
                max-height: 720px;
                object-fit: contain;
                display: block;
            }

            .entry__signature {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 18px;
                padding-top: 16px;
                border-top: 1px solid rgba(217, 207, 189, 0.9);
                font-family: Arial, sans-serif;
            }

            .entry__signature-author {
                font-size: 15px;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: var(--text);
            }

            .entry__signature-date {
                color: var(--muted);
                font-size: 13px;
            }

            .empty {
                padding: 48px 28px;
                text-align: center;
                color: var(--muted);
                font-family: Arial, sans-serif;
                background: var(--paper);
                border: 1px solid var(--line);
            }

            @media print {
                body {
                    background: #fff;
                }

                .album {
                    width: 100%;
                    margin: 0;
                }

                .cover,
                .entry,
                .empty {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
            }

            @media (max-width: 760px) {
                .album {
                    width: min(100% - 24px, 1080px);
                    margin: 12px auto;
                    gap: 16px;
                }

                .cover,
                .entry,
                .entry--text-only,
                .empty {
                    padding: 20px;
                }

                .cover h1 {
                    font-size: 34px;
                }

                .entry__author {
                    font-size: 22px;
                }

                .entry__text {
                    font-size: 20px;
                }

                .entry--text-only .entry__text {
                    padding-left: 22px;
                    font-size: 24px;
                }

                .entry--text-only .entry__text::before {
                    font-size: 42px;
                    top: -4px;
                }

                .entry__signature {
                    flex-direction: column;
                    align-items: flex-start;
                }
            }
        </style>
    </head>

    <body>
        <main class="album">
            <section class="cover">
                <p class="cover__eyebrow">Album de homenagens</p>
                <?php if ($coverImage !== ''): ?>
                    <img src="<?= e($coverImage) ?>" alt="<?= e($memorialName) ?>">
                <?php endif; ?>
                <h1><?= e($memorialName) ?></h1>
            </section>

            <?php if (!$posts): ?>
                <section class="empty">Nenhuma homenagem foi publicada neste memorial ate o momento.</section>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php
                    $hasText = trim((string) ($post['texto'] ?? '')) !== '';
                    $hasImage = !empty($post['imagem']);
                    ?>
                    <article class="entry<?= $hasText && !$hasImage ? ' entry--text-only' : '' ?>">
                        <div class="entry__meta">
                            <div class="entry__author"><?= e($post['nome_autor'] !== '' ? $post['nome_autor'] : 'Homenagem anonima') ?></div>
                            <?php if (!empty($post['criado_em'])): ?>
                                <div class="entry__date"><?= e(formatDateTimeBr($post['criado_em'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="entry__body">
                            <?php if ($hasText): ?>
                                <div class="entry__text"><?= $post['texto'] ?></div>
                            <?php endif; ?>
                            <?php if ($hasImage): ?>
                                <div class="entry__image">
                                    <img src="<?= e(appUrl($post['imagem'])) ?>" alt="Imagem da homenagem">
                                </div>
                            <?php endif; ?>
                            <?php if ($hasText && !$hasImage): ?>
                                <div class="entry__signature">
                                    <span class="entry__signature-author"><?= e($post['nome_autor'] !== '' ? $post['nome_autor'] : 'Homenagem anonima') ?></span>
                                    <?php if (!empty($post['criado_em'])): ?>
                                        <span class="entry__signature-date"><?= e(formatDateTimeBr($post['criado_em'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </body>

    </html>
<?php
} catch (Throwable $throwable) {
    http_response_code(400);
?>
    <!doctype html>
    <html lang="pt-BR">

    <head>
        <meta charset="utf-8">
        <title>Falha ao gerar album</title>
    </head>

    <body style="font-family: Arial, sans-serif; background: #111; color: #f5f5f5; padding: 32px;">
        <h1>Falha ao gerar album</h1>
        <p><?= e($throwable->getMessage()) ?></p>
    </body>

    </html>
<?php
}

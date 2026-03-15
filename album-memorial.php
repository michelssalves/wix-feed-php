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

        * { box-sizing: border-box; }

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

        .entry__text {
            font-size: 24px;
            line-height: 1.7;
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
        <p class="cover__meta">
            Registro gerado em <?= e(formatDateTimeBr(date('Y-m-d H:i:s'))) ?><br>
            Memorial key: <?= e($memorialKey) ?>
        </p>
    </section>

    <?php if (!$posts): ?>
        <section class="empty">Nenhuma homenagem foi publicada neste memorial ate o momento.</section>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <article class="entry">
                <div class="entry__meta">
                    <div class="entry__author"><?= e($post['nome_autor'] !== '' ? $post['nome_autor'] : 'Homenagem anonima') ?></div>
                    <?php if (!empty($post['criado_em'])): ?>
                        <div class="entry__date"><?= e(formatDateTimeBr($post['criado_em'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="entry__body">
                    <?php if (trim((string) ($post['texto'] ?? '')) !== ''): ?>
                        <div class="entry__text"><?= $post['texto'] ?></div>
                    <?php endif; ?>
                    <?php if (!empty($post['imagem'])): ?>
                        <div class="entry__image">
                            <img src="<?= e(appUrl($post['imagem'])) ?>" alt="Imagem da homenagem">
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

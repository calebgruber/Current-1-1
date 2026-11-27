<?php
/** @var string $content */
$title = $title ?? 'StageTrack';
$session = app()->session();
$shop = app()->hasDatabase() && app()->installed() ? setting('shop_name', 'StageTrack') : 'StageTrack';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($shop) ?></title>
    <script>
        (function () {
            var pref = localStorage.getItem('st-theme') || 'auto';
            var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('vendor/tabler/tabler.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/tabler-icons/tabler-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body class="st-guest" data-base="<?= e(url('/')) ?>" data-muted="1">
<div class="st-loader" id="st-loader" aria-hidden="true">
    <div class="st-loader-box"><span class="st-dot"></span><span class="st-dot"></span><span class="st-dot"></span><span class="st-loader-text">Thinking…</span></div>
</div>
<div class="page page-center">
    <div class="container <?= e($width ?? 'container-tight') ?> py-4">
        <div class="text-center mb-4">
            <span class="st-guest-brand"><span class="st-brand-mark"><i class="ti ti-bulb"></i></span><?= e($shop) ?></span>
        </div>
        <?php foreach (['success' => 'success', 'error' => 'danger'] as $key => $color): ?>
            <?php if ($msg = $session->get($key)): ?>
                <div class="alert alert-<?= $color ?>" role="alert"><?= e($msg) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?= $content ?>
    </div>
</div>
<script src="<?= e(asset('vendor/tabler/tabler.min.js')) ?>"></script>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>

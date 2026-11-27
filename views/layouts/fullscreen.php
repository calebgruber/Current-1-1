<?php
/** @var string $content */
$me = user();
$muted = ! (bool) setting('scan_sounds', '1') || ! empty($me['sound_muted']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(app()->session()->csrfToken()) ?>">
    <title><?= e($title ?? 'Scan station') ?> · <?= e(setting('shop_name', 'StageTrack')) ?></title>
    <script>
        (function () {
            var pref = localStorage.getItem('st-theme') || '<?= e($me['theme'] ?? 'auto') ?>';
            var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('vendor/tabler/tabler.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/tabler-icons/tabler-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body class="st-station-body" data-base="<?= e(url('/')) ?>" data-muted="<?= $muted ? '1' : '0' ?>">
<div class="st-loader" id="st-loader" aria-hidden="true">
    <div class="st-loader-box"><span class="st-dot"></span><span class="st-dot"></span><span class="st-dot"></span><span class="st-loader-text">Thinking…</span></div>
</div>
<?= $content ?>
<script src="<?= e(asset('vendor/tabler/tabler.min.js')) ?>"></script>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>

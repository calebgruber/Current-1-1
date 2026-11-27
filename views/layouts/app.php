<?php
/** @var string $content */
use App\Core\Navigation;

$module = $module ?? null;
$tab = $tab ?? null;
$title = $title ?? 'StageTrack';
$me = user();
$modules = Navigation::forUser(app()->auth());
$current = $module !== null ? ($modules[$module] ?? null) : null;
$theme = $me['theme'] ?? 'auto';
$muted = ! (bool) setting('scan_sounds', '1') || ! empty($me['sound_muted']);
$session = app()->session();
?>
<!doctype html>
<html lang="en" data-theme-pref="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($session->csrfToken()) ?>">
    <title><?= e($title) ?> · <?= e(setting('shop_name', 'StageTrack')) ?></title>
    <script>
        (function () {
            var pref = document.documentElement.getAttribute('data-theme-pref') || 'auto';
            var stored = localStorage.getItem('st-theme');
            if (stored) { pref = stored; }
            var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('vendor/tabler/tabler.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/tabler-icons/tabler-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body data-base="<?= e(url('/')) ?>" data-muted="<?= $muted ? '1' : '0' ?>"<?= $current ? ' style="--module-color: ' . e($current['color']) . '"' : '' ?>>
<div class="st-loader" id="st-loader" aria-hidden="true">
    <div class="st-loader-box">
        <span class="st-dot"></span><span class="st-dot"></span><span class="st-dot"></span>
        <span class="st-loader-text">Thinking…</span>
    </div>
</div>

<header class="st-topbar">
    <div class="st-topbar-inner">
        <a class="st-brand" href="<?= e(url('/')) ?>">
            <span class="st-brand-mark"><i class="ti ti-bulb"></i></span>
            <span class="st-brand-name"><?= e(setting('shop_name', 'StageTrack')) ?></span>
        </a>

        <nav class="st-modules" aria-label="Modules">
            <?php foreach ($modules as $key => $m): ?>
                <a href="<?= e(url($m['url'])) ?>" class="st-module<?= $key === $module ? ' active' : '' ?>" style="--c: <?= e($m['color']) ?>">
                    <i class="ti ti-<?= e($m['icon']) ?>"></i><span><?= e($m['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="st-tools">
            <form class="st-search" action="<?= e(url('/search')) ?>" method="get" role="search" data-scan-form>
                <i class="ti ti-scan"></i>
                <input type="search" name="q" placeholder="Search or scan…" autocomplete="off" value="<?= e(app()->request()?->query['q'] ?? '') ?>" aria-label="Search or scan a barcode" data-global-scan>
                <kbd>/</kbd>
            </form>
            <button type="button" class="st-icon-btn" data-toggle-sound title="Scan sounds on/off" aria-label="Toggle scan sounds">
                <i class="ti ti-volume" data-sound-on></i><i class="ti ti-volume-off" data-sound-off></i>
            </button>
            <button type="button" class="st-icon-btn" data-toggle-theme title="Light / dark mode" aria-label="Toggle dark mode">
                <i class="ti ti-moon" data-theme-icon="light"></i><i class="ti ti-sun" data-theme-icon="dark"></i>
            </button>
            <?php if ($me): ?>
                <div class="dropdown">
                    <button type="button" class="st-user" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar avatar-sm st-avatar"><?= e(mb_strtoupper(mb_substr($me['name'], 0, 1))) ?></span>
                        <span class="st-user-name d-none d-xl-inline"><?= e($me['name']) ?></span>
                        <i class="ti ti-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <span class="dropdown-header"><?= e(\App\Core\Auth::ROLES[$me['role']] ?? $me['role']) ?></span>
                        <a class="dropdown-item" href="<?= e(url('/account')) ?>"><i class="ti ti-user-circle me-2"></i>My account</a>
                        <div class="dropdown-divider"></div>
                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-danger"><i class="ti ti-logout me-2"></i>Sign out</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <button class="st-icon-btn d-lg-none" type="button" data-toggle-menu aria-label="Menu"><i class="ti ti-menu-2"></i></button>
        </div>
    </div>
</header>

<?php if ($current): ?>
<div class="st-subbar">
    <div class="st-subbar-inner">
        <div class="st-subbar-title"><i class="ti ti-<?= e($current['icon']) ?>"></i><?= e($current['label']) ?></div>
        <nav class="st-tabs" aria-label="<?= e($current['label']) ?> tabs">
            <?php foreach ($current['tabs'] as $key => $t): ?>
                <a class="st-tab<?= $key === $tab ? ' active' : '' ?>" href="<?= e(url($t[1])) ?>"><?= e($t[0]) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if (! empty($current['action'])): ?>
            <a class="btn btn-sm st-btn-module ms-auto" href="<?= e(url($current['action'][1])) ?>"><i class="ti ti-plus"></i><?= e($current['action'][0]) ?></a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<main class="st-main">
    <div class="container-xl">
        <?php foreach (['success' => 'success', 'error' => 'danger', 'info' => 'info'] as $key => $color): ?>
            <?php if ($msg = $session->get($key)): ?>
                <div class="alert alert-<?= $color ?> alert-dismissible st-flash" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ti ti-<?= $key === 'success' ? 'circle-check' : ($key === 'error' ? 'alert-triangle' : 'info-circle') ?> fs-2"></i>
                        <div><?= e($msg) ?></div>
                    </div>
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?= $content ?>
    </div>
</main>

<script src="<?= e(asset('vendor/tabler/tabler.min.js')) ?>"></script>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>

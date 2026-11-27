<?php
$tabs = [
    'overview' => ['Overview', '', 'layout-list'],
    'items' => ['Pull list', '/items', 'list-check'],
    'prep' => ['Prep & check out', '/prep', 'package-export'],
    'returns' => ['Returns', '/returns', 'package-import'],
    'messages' => ['Messages', '/messages', 'messages'],
    'documents' => ['Paperwork', '/documents', 'file-text'],
    'team' => ['Production team', '/team', 'users'],
];
$badge = ['items' => $counts['items'], 'messages' => $counts['messages'], 'documents' => $counts['documents'], 'team' => $counts['team']];
$overdue = $show['status'] === 'out' && $show['return_at'] < now();
?>
<div class="page-header st-page-header mb-3">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle st-mono"><?= e($show['reference']) ?><?= $show['venue'] ? ' · ' . e($show['venue']) : '' ?></div>
            <h2 class="page-title d-flex align-items-center gap-2"><?= e($show['title']) ?> <?= status_badge($show['status']) ?><?= $overdue ? '<span class="badge bg-red">Overdue</span>' : '' ?></h2>
            <div class="text-secondary mt-1 small d-flex flex-wrap gap-3">
                <span><i class="ti ti-truck"></i> Out <?= e(fmt_datetime($show['out_at'])) ?></span>
                <?php if ($show['opens_on']): ?><span><i class="ti ti-star"></i> Opens <?= e(fmt_date($show['opens_on'])) ?></span><?php endif; ?>
                <span class="<?= $overdue ? 'text-danger fw-bold' : '' ?>"><i class="ti ti-arrow-back-up"></i> Back <?= e(fmt_datetime($show['return_at'])) ?></span>
            </div>
        </div>
        <?php if (can('shows.manage')): ?>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a class="btn" href="<?= e(url('/shows/' . $show['id'] . '/edit')) ?>"><i class="ti ti-pencil"></i>Edit</a>
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" data-bs-toggle="dropdown">Status</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php foreach (['draft' => 'Draft', 'confirmed' => 'Confirmed', 'prepping' => 'Prepping', 'closed' => 'Close the show', 'cancelled' => 'Cancel the show'] as $value => $label): ?>
                                <form method="post" action="<?= e(url('/shows/' . $show['id'] . '/status')) ?>"<?= in_array($value, ['closed', 'cancelled'], true) ? ' data-confirm="' . e($label) . '? Anything packed but not sent will be unpacked."' : '' ?>>
                                    <?= csrf_field() ?><input type="hidden" name="status" value="<?= e($value) ?>">
                                    <button class="dropdown-item<?= $show['status'] === $value ? ' active' : '' ?>"><?= e($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<nav class="st-inner-tabs">
    <?php foreach ($tabs as $key => [$label, $path, $icon]): ?>
        <a href="<?= e(url('/shows/' . $show['id'] . $path)) ?>" class="<?= $showTab === $key ? 'active' : '' ?>">
            <i class="ti ti-<?= e($icon) ?>"></i><?= e($label) ?>
            <?php if (! empty($badge[$key])): ?><span class="badge bg-secondary-lt"><?= (int) $badge[$key] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

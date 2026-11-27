<?php $me = user(); ?>
<?= app()->view()->partial('partials/page_header', [
    'pretitle' => e(date('l j F')),
    'heading' => 'Good ' . (date('G') < 12 ? 'morning' : (date('G') < 18 ? 'afternoon' : 'evening')) . ', ' . explode(' ', $me['name'])[0],
    'actions' => (can('shows.manage') ? '<a href="' . e(url('/shows/create')) . '" class="btn btn-primary"><i class="ti ti-plus"></i>New show</a>' : '')
        . '<a href="' . e(url('/scan/station')) . '" class="btn"><i class="ti ti-maximize"></i>Scan station</a>',
]) ?>

<div class="row row-cards mb-3">
    <?php foreach ([
        ['Upcoming shows', $stats['upcoming'], 'masks-theater', '#ae3ec9', '/shows'],
        ['Shows out now', $stats['out'], 'truck-delivery', '#f76707', '/shows?view=out'],
        ['Units out of the shop', $stats['units_out'], 'package-export', '#4263eb', '/warehouse/out'],
        ['Units in repair', $stats['repairs'], 'tool', '#d63939', '/warehouse/repairs'],
    ] as [$label, $value, $icon, $color, $link]): ?>
        <div class="col-sm-6 col-xl-3">
            <a class="card card-link st-stat" style="--c: <?= e($color) ?>" href="<?= e(url($link)) ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar" style="background: <?= e($color) ?>1f; color: <?= e($color) ?>"><i class="ti ti-<?= e($icon) ?> fs-2"></i></span>
                    <div>
                        <div class="h1"><?= (int) $value ?></div>
                        <div class="text-secondary"><?= e($label) ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row row-cards">
    <div class="col-lg-8">
        <?php if ($overdue): ?>
            <div class="alert alert-danger">
                <div class="d-flex gap-2"><i class="ti ti-alarm fs-2"></i>
                    <div><strong>Overdue returns:</strong>
                        <?php foreach ($overdue as $i => $s): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('/shows/' . $s['id'] . '/returns')) ?>"><?= e($s['title']) ?></a> (due <?= e(fmt_date($s['return_at'])) ?>)<?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-calendar-event"></i>Coming up in the next 30 days</h3>
                <div class="card-actions"><a href="<?= e(url('/calendar')) ?>" class="btn btn-sm">Calendar</a></div>
            </div>
            <?php if ($upcoming): ?>
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover card-table">
                        <thead><tr><th>Show</th><th>Venue</th><th>Goes out</th><th>Back</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($upcoming as $s): ?>
                            <tr data-href="<?= e(url('/shows/' . $s['id'])) ?>">
                                <td><div class="fw-semibold"><?= e($s['title']) ?></div><div class="text-secondary small st-mono"><?= e($s['reference']) ?></div></td>
                                <td><?= e($s['venue'] ?: '—') ?></td>
                                <td><?= e(fmt_datetime($s['out_at'])) ?></td>
                                <td><?= e(fmt_date($s['return_at'])) ?></td>
                                <td><?= status_badge($s['status']) ?></td>
                                <td class="text-end"><a class="btn btn-sm" href="<?= e(url('/shows/' . $s['id'] . '/prep')) ?>"><i class="ti ti-package"></i>Prep</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= app()->view()->partial('partials/empty', ['icon' => 'calendar-off', 'title' => 'Nothing going out in the next 30 days']) ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-truck-delivery"></i>Out with shows right now</h3></div>
            <?php if ($out): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($out as $s): ?>
                        <a class="list-group-item list-group-item-action d-flex align-items-center" href="<?= e(url('/shows/' . $s['id'] . '/returns')) ?>">
                            <div class="flex-fill"><div class="fw-semibold"><?= e($s['title']) ?></div><div class="text-secondary small"><?= e($s['venue'] ?: '') ?></div></div>
                            <div class="text-end small <?= $s['return_at'] < now() ? 'text-danger fw-bold' : 'text-secondary' ?>">Due back <?= e(fmt_date($s['return_at'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= app()->view()->partial('partials/empty', ['icon' => 'building-warehouse', 'title' => 'Everything is in the shop']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title"><i class="ti ti-scan"></i>Find anything</h3>
                <form action="<?= e(url('/search')) ?>" method="get" data-scan-form>
                    <div class="input-group">
                        <input class="form-control" name="q" placeholder="Scan a barcode or type a name" autocomplete="off">
                        <button class="btn btn-primary" type="submit"><i class="ti ti-search"></i>Go</button>
                    </div>
                    <div class="form-hint mt-2">Scanning an asset tag, barcode, case or show reference opens it straight away.</div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-messages"></i>Latest from shows</h3></div>
            <?php if ($messages): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($messages as $m): ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(url('/shows/' . $m['show_id'] . '/messages')) ?>">
                            <div class="d-flex justify-content-between small text-secondary"><span><?= e($m['author_name']) ?></span><span><?= e(fmt_date($m['created_at'], 'j M')) ?></span></div>
                            <div class="fw-semibold small"><?= e($m['show_title']) ?></div>
                            <div class="text-truncate"><?= e($m['body']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary">No messages from shows yet.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-history"></i>Recent activity</h3></div>
            <?php if ($activity): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($activity as $a): ?>
                        <div class="list-group-item small">
                            <div><?= e($a['description']) ?></div>
                            <div class="text-secondary"><?= e($a['user_name'] ?? 'Show team') ?><?= $a['reference'] ? ' · ' . e($a['reference']) : '' ?> · <?= e(fmt_datetime($a['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary">Nothing has happened yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

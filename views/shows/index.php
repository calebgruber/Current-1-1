<?php
$headings = ['upcoming' => 'Upcoming shows', 'out' => 'Out with shows now', 'past' => 'Past shows', 'all' => 'All shows'];
?>
<?= app()->view()->partial('partials/page_header', [
    'heading' => $headings[$tab],
    'actions' => '<form method="get" class="d-flex gap-2"><input type="hidden" name="view" value="' . e($view) . '"><input class="form-control" name="q" value="' . e($q) . '" placeholder="Search shows…"><button class="btn btn-icon" aria-label="Search"><i class="ti ti-search"></i></button></form>',
]) ?>
<div class="card">
    <?php if ($shows): ?>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead><tr><th>Show</th><th>Venue</th><th>Goes out</th><th>Opens</th><th>Comes back</th><th class="text-end">Units</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($shows as $s): ?>
                    <tr data-href="<?= e(url('/shows/' . $s['id'])) ?>">
                        <td><a href="<?= e(url('/shows/' . $s['id'])) ?>" class="fw-semibold text-reset"><?= e($s['title']) ?></a><div class="text-secondary small st-mono"><?= e($s['reference']) ?><?= $s['department'] ? ' · ' . e($s['department']) : '' ?></div></td>
                        <td><?= e($s['venue'] ?: '—') ?></td>
                        <td><?= e(fmt_datetime($s['out_at'])) ?></td>
                        <td><?= e(fmt_date($s['opens_on'])) ?></td>
                        <td class="<?= $s['status'] === 'out' && $s['return_at'] < now() ? 'text-danger fw-bold' : '' ?>"><?= e(fmt_datetime($s['return_at'])) ?></td>
                        <td class="text-end"><?= (int) $s['units'] ?></td>
                        <td><?= status_badge($s['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= app()->view()->partial('partials/empty', [
            'icon' => 'masks-theater',
            'title' => $q !== '' ? 'No shows match “' . $q . '”' : 'No shows here yet',
            'text' => 'Shows hold the pull list, prep, returns, paperwork and conversations with the production.',
            'action' => can('shows.manage') ? '<a class="btn btn-primary" href="' . e(url('/shows/create')) . '"><i class="ti ti-plus"></i>New show</a>' : '',
        ]) ?>
    <?php endif; ?>
</div>

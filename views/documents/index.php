<?php
$showOptions = '<option value="">All shows</option>';
foreach ($shows as $s) {
    $showOptions .= '<option value="' . (int) $s['id'] . '"' . ((int) $s['id'] === $showId ? ' selected' : '') . '>' . e($s['title']) . ' (' . e($s['reference']) . ')</option>';
}
?>
<?= app()->view()->partial('partials/page_header', [
    'heading' => 'Paperwork',
    'subtitle' => 'Every pull sheet, receipt and notice made for a show. To make new paperwork, open a show and go to its Paperwork tab.',
    'actions' => '<form method="get" class="d-flex flex-wrap gap-2">'
        . '<select class="form-select w-auto" name="show_id" onchange="this.form.submit()" aria-label="Filter by show">' . $showOptions . '</select>'
        . '<input class="form-control w-auto" name="q" value="' . e($q) . '" placeholder="Search paperwork…">'
        . '<button class="btn btn-icon" aria-label="Search"><i class="ti ti-search"></i></button></form>',
]) ?>
<div class="card">
    <?php if ($documents): ?>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead><tr><th>Title</th><th>Show</th><th>Created by</th><th>Created</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($documents as $d): ?>
                    <tr data-href="<?= e(url('/documents/' . $d['id'])) ?>">
                        <td><a href="<?= e(url('/documents/' . $d['id'])) ?>" class="fw-semibold text-reset"><i class="ti ti-file-text text-secondary me-1"></i><?= e($d['title']) ?></a></td>
                        <td>
                            <?php if ($d['show_id']): ?>
                                <a href="<?= e(url('/shows/' . $d['show_id'] . '/documents')) ?>"><?= e($d['show_title']) ?></a>
                                <div class="text-secondary small st-mono"><?= e($d['show_reference']) ?></div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= e($d['user_name'] ?: '—') ?></td>
                        <td class="text-nowrap"><?= e(fmt_datetime($d['created_at'])) ?></td>
                        <td class="text-end"><?= $d['shared'] ? '<span class="badge bg-green-lt"><i class="ti ti-share me-1"></i>Shared with show</span>' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= app()->view()->partial('partials/empty', [
            'icon' => 'file-text',
            'title' => $q !== '' || $showId ? 'No paperwork matches' : 'No paperwork yet',
            'text' => 'Open a show and use its Paperwork tab to make pull sheets, receipts and more.',
            'action' => '<a class="btn btn-primary" href="' . e(url('/shows')) . '"><i class="ti ti-masks-theater"></i>Go to shows</a>',
        ]) ?>
    <?php endif; ?>
</div>

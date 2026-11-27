<?php
$printUrl = url('/documents/' . $document['id'] . '/print');
$actions = '<a class="btn" href="' . e($printUrl) . '" target="_blank" rel="noopener"><i class="ti ti-printer"></i>Print</a>';
if (can('documents.generate')) {
    $actions .= '<form method="post" action="' . e(url('/documents/' . $document['id'] . '/share')) . '" class="d-inline">' . csrf_field()
        . ($document['shared']
            ? '<button class="btn btn-outline-secondary"><i class="ti ti-share-off"></i>Stop sharing</button>'
            : '<button class="btn"><i class="ti ti-share"></i>Share with show</button>')
        . '</form>';
}
if ($show && can('messages')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('/shows/' . $show['id'] . '/messages')) . '"><i class="ti ti-send"></i>Send to show</a>';
}
if (can('delete')) {
    $actions .= app()->view()->partial('partials/delete', [
        'action' => '/documents/' . $document['id'] . '/delete',
        'confirm' => 'Delete this paperwork? This cannot be undone.',
    ]);
}
$subtitle = 'Made ' . e(fmt_datetime($document['created_at'])) . ($author ? ' by ' . e($author) : '');
if ($document['shared']) {
    $subtitle .= ' <span class="badge bg-green-lt ms-2"><i class="ti ti-share me-1"></i>Shared with show</span>';
}
?>
<?= app()->view()->partial('partials/page_header', [
    'pretitle' => $show
        ? '<a href="' . e(url('/shows/' . $show['id'] . '/documents')) . '"><i class="ti ti-arrow-left"></i> ' . e($show['title']) . ' · ' . e($show['reference']) . '</a>'
        : '<a href="' . e(url('/documents')) . '"><i class="ti ti-arrow-left"></i> All paperwork</a>',
    'heading' => $document['title'],
    'subtitle' => $subtitle,
    'actions' => $actions,
]) ?>
<?php if ($document['shared'] && $show): ?>
    <div class="alert alert-info">The production team can open this from their show portal.</div>
<?php endif; ?>
<?php /* Sandboxed without allow-scripts: template HTML can style itself but never run code here. */ ?>
<iframe class="st-doc-frame" title="<?= e($document['title']) ?>" sandbox="allow-same-origin allow-modals" srcdoc="<?= e($document['body']) ?>"></iframe>
<p class="text-secondary small mt-2"><i class="ti ti-info-circle"></i> Paperwork is a snapshot from when it was made. If the pull list changes, make a fresh copy from the show’s Paperwork tab.</p>

<div class="empty st-error">
    <div class="empty-header"><?= e($status) ?></div>
    <p class="empty-title"><?= e(match ((int) $status) {
        403 => 'You do not have access to this',
        404 => 'We could not find that page',
        419 => 'This page expired',
        default => 'Something went wrong',
    }) ?></p>
    <p class="empty-subtitle text-secondary">
        <?= e(match ((int) $status) {
            403 => 'Your role does not allow this action. Ask the shop manager if you need access.',
            404 => 'It may have been deleted or the link is wrong.',
            419 => 'Your session timed out. Go back, refresh the page and try again.',
            default => $message,
        }) ?>
    </p>
    <div class="empty-action">
        <a href="<?= e(url('/')) ?>" class="btn btn-primary"><i class="ti ti-arrow-left me-1"></i>Back to the dashboard</a>
    </div>
</div>

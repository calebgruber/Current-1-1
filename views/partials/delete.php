<form method="post" action="<?= e(url($action)) ?>" class="d-inline" data-confirm="<?= e($confirm ?? 'Delete this? This cannot be undone.') ?>">
    <?= csrf_field() ?>
    <button class="btn <?= e($class ?? 'btn-outline-danger') ?>" type="submit"><i class="ti ti-trash"></i><?= e($label ?? 'Delete') ?></button>
</form>

<div class="empty py-5">
    <div class="empty-icon"><i class="ti ti-<?= e($icon ?? 'mood-empty') ?> fs-1"></i></div>
    <p class="empty-title"><?= e($title) ?></p>
    <?php if (! empty($text)): ?><p class="empty-subtitle text-secondary"><?= e($text) ?></p><?php endif; ?>
    <?php if (! empty($action)): ?><div class="empty-action"><?= $action ?></div><?php endif; ?>
</div>

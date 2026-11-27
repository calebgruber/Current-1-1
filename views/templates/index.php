<?= app()->view()->partial('partials/page_header', [
    'heading' => 'Paperwork templates',
    'subtitle' => 'Change the wording and layout of the paperwork the shop makes. Paperwork already made is not changed.',
]) ?>
<div class="card">
    <?php if ($templates): ?>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead><tr><th>Template</th><th>Last updated</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                    <tr data-href="<?= e(url('/templates/' . $t['id'])) ?>">
                        <td>
                            <a class="fw-semibold text-reset" href="<?= e(url('/templates/' . $t['id'])) ?>"><?= e($t['name']) ?></a>
                            <div class="text-secondary small"><?= e($t['description'] ?? '') ?></div>
                        </td>
                        <td class="text-secondary small text-nowrap"><?= e(fmt_datetime($t['updated_at'])) ?><?= $t['updated_by_name'] ? '<div>by ' . e($t['updated_by_name']) . '</div>' : '' ?></td>
                        <td class="text-end"><a class="btn btn-sm" href="<?= e(url('/templates/' . $t['id'])) ?>"><i class="ti ti-pencil"></i>Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= app()->view()->partial('partials/empty', [
            'icon' => 'template',
            'title' => 'No templates installed',
            'text' => 'Reinstall the built-in paperwork from Admin → Database.',
            'action' => can('admin') ? '<a class="btn btn-primary" href="' . e(url('/admin/database')) . '"><i class="ti ti-database"></i>Go to Database</a>' : '',
        ]) ?>
    <?php endif; ?>
    <div class="card-footer text-secondary small">
        <i class="ti ti-info-circle"></i> Missing templates? An admin can reinstall them from
        <?php if (can('admin')): ?><a href="<?= e(url('/admin/database')) ?>">Admin → Database</a><?php else: ?>Admin → Database<?php endif; ?>.
        Your edited templates are kept.
    </div>
</div>

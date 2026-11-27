<?= app()->view()->partial('partials/page_header', [
    'heading' => 'Contacts',
    'subtitle' => 'Directors, stage managers, designers and anyone else the shop works with.',
    'actions' => '<form method="get" class="d-flex gap-2"><input class="form-control" name="q" value="' . e($q) . '" placeholder="Search people…"><button class="btn btn-icon" aria-label="Search"><i class="ti ti-search"></i></button></form>'
        . (can('contacts.manage') ? '<a class="btn btn-primary" href="' . e(url('/contacts/create')) . '"><i class="ti ti-plus"></i>New contact</a>' : ''),
]) ?>
<div class="card">
    <?php if ($contacts): ?>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead><tr><th>Name</th><th>Organization</th><th>Role</th><th>Email</th><th>Phone</th><th class="text-end">Shows</th></tr></thead>
                <tbody>
                <?php foreach ($contacts as $c): ?>
                    <tr data-href="<?= e(url('/contacts/' . $c['id'])) ?>">
                        <td><a href="<?= e(url('/contacts/' . $c['id'])) ?>" class="fw-semibold text-reset"><?= e($c['name']) ?></a></td>
                        <td><?= e($c['organization'] ?: '—') ?></td>
                        <td><?= e($c['role'] ?: '—') ?></td>
                        <td><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '—' ?></td>
                        <td><?= $c['phone'] ? '<a href="tel:' . e($c['phone']) . '">' . e($c['phone']) . '</a>' : '—' ?></td>
                        <td class="text-end"><?= (int) $c['show_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= app()->view()->partial('partials/empty', [
            'icon' => 'address-book',
            'title' => $q !== '' ? 'No one matches “' . $q . '”' : 'No contacts yet',
            'text' => 'Add the people you work with so you can put them on shows and paperwork.',
            'action' => can('contacts.manage') ? '<a class="btn btn-primary" href="' . e(url('/contacts/create')) . '"><i class="ti ti-plus"></i>New contact</a>' : '',
        ]) ?>
    <?php endif; ?>
</div>

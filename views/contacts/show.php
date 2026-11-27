<?php $f = fn (array $o) => app()->view()->partial('partials/field', $o); ?>
<?= app()->view()->partial('partials/page_header', [
    'heading' => $contact['name'],
    'pretitle' => '<a href="' . e(url('/contacts')) . '">Contacts</a>',
    'subtitle' => e(implode(' · ', array_filter([$contact['role'], $contact['organization']]))),
    'actions' => can('delete') ? app()->view()->partial('partials/delete', [
        'action' => '/contacts/' . $contact['id'] . '/delete',
        'confirm' => 'Delete ' . $contact['name'] . '? They will also be removed from every show team.',
    ]) : '',
]) ?>
<div class="row row-cards">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-id-badge-2"></i>Details</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-4">Role</dt><dd class="col-8"><?= e($contact['role'] ?: '—') ?></dd>
                    <dt class="col-4">Organization</dt><dd class="col-8"><?= e($contact['organization'] ?: '—') ?></dd>
                    <dt class="col-4">Email</dt><dd class="col-8"><?= $contact['email'] ? '<a href="mailto:' . e($contact['email']) . '">' . e($contact['email']) . '</a>' : '—' ?></dd>
                    <dt class="col-4">Phone</dt><dd class="col-8"><?= $contact['phone'] ? '<a href="tel:' . e($contact['phone']) . '">' . e($contact['phone']) . '</a>' : '—' ?></dd>
                </dl>
                <?php if ($contact['notes']): ?>
                    <div class="mt-3 text-secondary" style="white-space: pre-line"><?= e($contact['notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-masks-theater"></i>Shows</h3></div>
            <?php if ($shows): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($shows as $s): ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(url('/shows/' . $s['id'])) ?>">
                            <div class="d-flex align-items-center">
                                <div class="flex-fill">
                                    <div class="fw-semibold"><?= e($s['title']) ?></div>
                                    <div class="text-secondary small"><?= e($s['show_role'] ?: 'On the team') ?> · <?= e(fmt_date($s['out_at'])) ?></div>
                                </div>
                                <?= status_badge($s['status']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= app()->view()->partial('partials/empty', ['icon' => 'masks-theater', 'title' => 'Not on any shows yet', 'text' => 'Add them from a show’s Team tab.']) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if (can('contacts.manage')): ?>
            <form class="card" method="post" action="<?= e(url('/contacts/' . $contact['id'])) ?>">
                <?= csrf_field() ?>
                <div class="card-header"><h3 class="card-title"><i class="ti ti-pencil"></i>Edit details</h3></div>
                <div class="card-body">
                    <div class="row">
                        <?= $f(['name' => 'name', 'label' => 'Name', 'value' => $contact['name'], 'required' => true, 'col' => 'col-md-6 mb-3']) ?>
                        <?= $f(['name' => 'role', 'label' => 'Role', 'value' => $contact['role'], 'placeholder' => 'e.g. Stage manager', 'col' => 'col-md-6 mb-3']) ?>
                        <?= $f(['name' => 'organization', 'label' => 'Organization', 'value' => $contact['organization'], 'col' => 'col-md-6 mb-3']) ?>
                        <?= $f(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $contact['email'], 'col' => 'col-md-6 mb-3']) ?>
                        <?= $f(['name' => 'phone', 'label' => 'Phone', 'value' => $contact['phone'], 'col' => 'col-md-6 mb-3']) ?>
                    </div>
                    <?= $f(['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'value' => $contact['notes']]) ?>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i>Save changes</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>

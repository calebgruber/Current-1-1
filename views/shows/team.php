<?= app()->view()->partial('shows/_header', compact('show', 'counts', 'showTab')) ?>
<div class="row row-cards">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-users"></i>Production team</h3></div>
            <?php if ($team): ?>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Name</th><th>Role on this show</th><th>Email</th><th>Phone</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($team as $c): ?>
                            <tr>
                                <td><a class="fw-semibold" href="<?= e(url('/contacts/' . $c['id'])) ?>"><?= e($c['name']) ?></a><div class="text-secondary small"><?= e($c['organization'] ?? '') ?></div></td>
                                <td><?= e($c['show_role'] ?: '—') ?></td>
                                <td><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '—' ?></td>
                                <td><?= e($c['phone'] ?: '—') ?></td>
                                <td class="text-end">
                                    <?php if (can('shows.manage')): ?>
                                        <form method="post" action="<?= e(url('/shows/' . $show['id'] . '/team/' . $c['id'] . '/delete')) ?>" data-confirm="Remove <?= e($c['name']) ?> from this show?">
                                            <?= csrf_field() ?><button class="btn btn-sm btn-ghost-danger btn-icon" aria-label="Remove"><i class="ti ti-x"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= app()->view()->partial('partials/empty', ['icon' => 'users', 'title' => 'No one on the team yet', 'text' => 'Add the director, stage manager and designers so paperwork lists the right people.']) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <?php if (can('shows.manage')): ?>
            <form class="card" method="post" action="<?= e(url('/shows/' . $show['id'] . '/team')) ?>">
                <?= csrf_field() ?>
                <div class="card-header"><h3 class="card-title"><i class="ti ti-user-plus"></i>Add someone</h3></div>
                <div class="card-body">
                    <?php $options = []; foreach ($contacts as $c) { $options[$c['id']] = $c['name'] . ($c['role'] ? ' — ' . $c['role'] : ''); } ?>
                    <?= app()->view()->partial('partials/field', ['name' => 'contact_id', 'label' => 'Existing contact', 'type' => 'select', 'options' => $options, 'blank' => '— New person (fill in below) —']) ?>
                    <?= app()->view()->partial('partials/field', ['name' => 'role', 'label' => 'Role on this show', 'placeholder' => 'e.g. Stage manager', 'attrs' => 'list="roles"']) ?>
                    <datalist id="roles"><?php foreach (['Director', 'Stage manager', 'Assistant stage manager', 'Production manager', 'Lighting designer', 'Sound designer', 'Master electrician', 'A1 / Sound engineer', 'Board operator', 'Faculty advisor'] as $r): ?><option value="<?= e($r) ?>"><?php endforeach; ?></datalist>
                    <div class="hr-text my-3">or a new person</div>
                    <?= app()->view()->partial('partials/field', ['name' => 'name', 'label' => 'Name']) ?>
                    <?= app()->view()->partial('partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email']) ?>
                    <?= app()->view()->partial('partials/field', ['name' => 'phone', 'label' => 'Phone']) ?>
                </div>
                <div class="card-footer"><button class="btn btn-primary w-100"><i class="ti ti-plus"></i>Add to team</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?= app()->view()->partial('shows/_header', compact('show', 'counts', 'showTab')) ?>
<?php
$steps = [
    ['Plan the pull list', $counts['items'] > 0, '/items'],
    ['Pack it in the shop', $summary['prepped'] + $summary['sent'] > 0, '/prep'],
    ['Check it out to the show', $summary['sent'] > 0, '/prep'],
    ['Get it all back', in_array($show['status'], ['returned', 'closed'], true), '/returns'],
];
?>
<div class="card mb-3">
    <div class="card-body">
        <ul class="steps steps-counter steps-purple my-2">
            <?php $active = false; foreach ($steps as [$label, $done, $path]): ?>
                <?php $isActive = ! $done && ! $active; $active = $active || $isActive; ?>
                <li class="step-item<?= $isActive ? ' active' : '' ?>"><a class="text-reset" href="<?= e(url('/shows/' . $show['id'] . $path)) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="row row-cards">
    <div class="col-lg-8">
        <?php if ($shortages): ?>
            <div class="alert alert-warning">
                <h4 class="alert-title"><i class="ti ti-alert-triangle"></i> Not enough stock for these dates</h4>
                <ul class="mb-0">
                    <?php foreach ($shortages as $itemId => $s): ?>
                        <li><strong><?= e($shortageNames[$itemId]) ?></strong>: need <?= (int) $s['required'] ?>, the shop owns <?= (int) $s['stock'] ?><?= $s['other_shows'] ? ' and ' . (int) $s['other_shows'] . ' are booked by other shows' : '' ?> — short by <?= (int) $s['shortage'] ?>.</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row row-cards mb-3">
            <?php foreach ([['On the pull list', $summary['required'], '#ae3ec9'], ['Packed, not sent', $summary['prepped'], '#f59f00'], ['Out with the show', $summary['out'], '#4263eb'], ['Returned', $summary['returned'], '#2fb344']] as [$label, $value, $color]): ?>
                <div class="col-6 col-md-3">
                    <div class="card st-stat" style="--c: <?= e($color) ?>"><div class="card-body py-3"><div class="h1"><?= (int) $value ?></div><div class="text-secondary small"><?= e($label) ?></div></div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-box"></i>Containers with this show</h3></div>
            <?php if ($summary['containers']): ?>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Container</th><th>Comes back</th><th class="text-end">Units inside</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($summary['containers'] as $c): ?>
                            <tr>
                                <td><a href="<?= e(url('/containers/' . $c['container_id'])) ?>"><?= e($c['name']) ?></a> <span class="text-secondary small st-mono"><?= e($c['barcode']) ?></span></td>
                                <td><?= $c['return_after_transport'] ? '<span class="badge bg-orange-lt">Straight after transport</span>' : '<span class="badge bg-blue-lt">At strike</span>' ?></td>
                                <td class="text-end"><?= array_sum(array_column($c['contents'], 'quantity')) ?></td>
                                <td><?= status_badge($c['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary">No road cases or tubs yet. Add them on the <a href="<?= e(url('/shows/' . $show['id'] . '/prep')) ?>">Prep</a> tab.</div>
            <?php endif; ?>
        </div>

        <?php if ($show['notes']): ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-notes"></i>Notes</h3></div>
                <div class="card-body" style="white-space: pre-wrap"><?= e($show['notes']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-users"></i>Production team</h3></div>
            <?php if ($team): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($team as $c): ?>
                        <div class="list-group-item">
                            <div class="fw-semibold"><?= e($c['name']) ?></div>
                            <div class="text-secondary small"><?= e($c['show_role'] ?: $c['role'] ?: '') ?></div>
                            <?php if ($c['email']): ?><a class="small" href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary">No one yet. <a href="<?= e(url('/shows/' . $show['id'] . '/team')) ?>">Add the team</a>.</div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-history"></i>Timeline</h3></div>
            <?php if ($activity): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($activity as $a): ?>
                        <div class="list-group-item small"><div><?= e($a['description']) ?></div><div class="text-secondary"><?= e($a['user_name'] ?? 'Show team') ?> · <?= e(fmt_datetime($a['created_at'])) ?></div></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary">Nothing yet.</div>
            <?php endif; ?>
            <?php if (can('delete')): ?>
                <div class="card-footer text-end">
                    <?= app()->view()->partial('partials/delete', ['action' => '/shows/' . $show['id'] . '/delete', 'label' => 'Delete show', 'class' => 'btn-sm btn-ghost-danger', 'confirm' => 'Delete this show, its pull list, paperwork and messages? This cannot be undone.']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

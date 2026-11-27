<?= app()->view()->partial('shows/_header', compact('show', 'counts', 'showTab')) ?>
<?php
$sid = (int) $show['id'];
$base = '/shows/' . $sid;
$canScan = can('warehouse') && $canEdit;
$openContainers = array_values(array_filter($summary['containers'], fn ($c) => $c['status'] !== 'returned'));
$packedRow = function (array $row) use ($base, $canScan): string {
    $html = '<tr><td>' . e($row['item_name']) . ($row['asset_tag'] ? ' <span class="badge bg-secondary-lt st-mono">' . e($row['asset_tag']) . '</span>' : '') . '</td>'
        . '<td class="text-end">' . (int) $row['quantity'] . '</td><td>' . status_badge($row['status']) . '</td><td class="text-end">';
    if ($canScan && $row['status'] === 'prepped') {
        $html .= '<form method="post" action="' . e(url($base . '/prep/' . $row['id'] . '/delete')) . '">' . csrf_field()
            . '<button class="btn btn-sm btn-ghost-danger btn-icon" title="Unpack" aria-label="Unpack"><i class="ti ti-x"></i></button></form>';
    }

    return $html . '</td></tr>';
};
?>
<div class="row row-cards">
    <div class="col-lg-7">
        <?php if ($canScan): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <form class="st-scanbox" method="post" action="<?= e(url($base . '/prep')) ?>" data-ajax-scan="#prep-result" data-reload="#prep-live, #prep-checkout, #prep-progress">
                        <?= csrf_field() ?>
                        <label class="form-label fw-bold" for="prep-code"><i class="ti ti-barcode"></i> Scan to pack</label>
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control form-control-lg" id="prep-code" name="code" placeholder="Scan a barcode…" autocomplete="off" autofocus data-scan-input></div>
                            <div class="col-6 col-md-2"><input class="form-control form-control-lg" type="number" min="1" name="quantity" value="1" title="Quantity (bulk items)" aria-label="Quantity"></div>
                            <div class="col-6 col-md-4">
                                <select class="form-select form-select-lg" name="container_id" aria-label="Pack into">
                                    <option value="">Loose (no case)</option>
                                    <?php foreach ($openContainers as $c): ?><option value="<?= (int) $c['container_id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="text-secondary small mt-2">Scan a road case first to start packing into it. Serial units pack one at a time; bulk items use the quantity box.</div>
                        <div id="prep-result" class="mt-3"></div>
                    </form>
                </div>
            </div>
        <?php elseif (! $canEdit): ?>
            <div class="alert alert-info">This show is <?= e($show['status']) ?>, so packing is closed.</div>
        <?php endif; ?>

        <div id="prep-live">
            <?php foreach ($summary['containers'] as $c): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-box"></i><?= e($c['name']) ?> <span class="text-secondary small st-mono ms-2"><?= e($c['barcode']) ?></span></h3>
                        <div class="card-actions d-flex gap-2 align-items-center">
                            <?= status_badge($c['status']) ?>
                            <?php if ($canScan): ?>
                                <form method="post" action="<?= e(url($base . '/containers/' . $c['id'])) ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <label class="form-check form-switch m-0" title="Comes back to the shop right after transport">
                                        <input class="form-check-input" type="checkbox" name="return_after_transport" value="1" onchange="this.form.submit()"<?= $c['return_after_transport'] ? ' checked' : '' ?>>
                                        <span class="form-check-label small">Back after transport</span>
                                    </label>
                                </form>
                                <?php if ($c['status'] === 'prepped'): ?>
                                    <form method="post" action="<?= e(url($base . '/containers/' . $c['id'] . '/delete')) ?>" class="m-0" data-confirm="Take <?= e($c['name']) ?> off this show? Anything inside becomes loose.">
                                        <?= csrf_field() ?><button class="btn btn-sm btn-ghost-danger btn-icon" aria-label="Remove container"><i class="ti ti-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($c['contents']): ?>
                        <table class="table card-table table-sm table-vcenter">
                            <tbody><?php foreach ($c['contents'] as $row) { echo $packedRow($row); } ?></tbody>
                        </table>
                    <?php else: ?>
                        <div class="card-body text-secondary small py-2">Empty – <?= $c['return_after_transport'] ? 'goes out and comes straight back after transport.' : 'scan items while this case is selected.' ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-packages"></i>Loose (not in a case)</h3></div>
                <?php if ($loose): ?>
                    <table class="table card-table table-sm table-vcenter"><tbody><?php foreach ($loose as $row) { echo $packedRow($row); } ?></tbody></table>
                <?php else: ?>
                    <div class="card-body text-secondary small py-2">Nothing loose.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if ($canScan): ?>
            <div class="card mb-3" id="prep-checkout">
                <div class="card-body">
                    <form method="post" action="<?= e(url($base . '/checkout')) ?>" data-confirm="Send everything packed out to <?= e($show['title']) ?>?">
                        <?= csrf_field() ?>
                        <button class="btn btn-success btn-lg w-100" <?= $summary['prepped'] ? '' : 'disabled' ?>><i class="ti ti-truck-delivery"></i>Check out <?= (int) $summary['prepped'] ?> packed <?= $summary['prepped'] === 1 ? 'unit' : 'units' ?></button>
                    </form>
                    <div class="text-secondary small mt-2 text-center">You can keep packing after checking out — late additions go out with the next check out.</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-box"></i>Lend a container</h3></div>
                <form class="card-body" method="post" action="<?= e(url($base . '/containers')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <select class="form-select" name="container_id" required aria-label="Container">
                            <option value="">Choose a road case, trunk or tub…</option>
                            <?php foreach ($availableContainers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?><?= $c['barcode'] ? ' (' . e($c['barcode']) . ')' : '' ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <label class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="return_after_transport" value="1">
                        <span class="form-check-label">Comes back right after transport <span class="text-secondary small d-block">The case is unloaded at the venue and returns empty; its contents stay with the show.</span></span>
                    </label>
                    <button class="btn w-100"><i class="ti ti-plus"></i>Add container</button>
                </form>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-hand-finger"></i>Pack without a barcode</h3></div>
                <form class="card-body" method="post" action="<?= e(url($base . '/prep/item')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-12">
                            <select class="form-select" name="item_id" required aria-label="Bulk item">
                                <option value="">Bulk item…</option>
                                <?php foreach ($bulkItems as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4"><input class="form-control" type="number" min="1" name="quantity" value="1" aria-label="Quantity"></div>
                        <div class="col-8">
                            <select class="form-select" name="container_id" aria-label="Pack into">
                                <option value="">Loose</option>
                                <?php foreach ($openContainers as $c): ?><option value="<?= (int) $c['container_id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><button class="btn w-100"><i class="ti ti-plus"></i>Pack</button></div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card" id="prep-progress">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-list-check"></i>Pull list progress</h3></div>
            <?php if ($progress): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($progress as $p): $pct = $p['needed'] ? min(100, (int) round($p['packed'] / $p['needed'] * 100)) : 100; ?>
                        <div class="list-group-item py-2">
                            <div class="d-flex justify-content-between small">
                                <span class="<?= $p['remaining'] === 0 ? 'text-secondary' : 'fw-semibold' ?>"><?= e($p['name']) ?></span>
                                <span class="<?= $p['remaining'] === 0 ? 'text-success' : '' ?>"><?= (int) $p['packed'] ?> / <?= (int) $p['needed'] ?><?= $p['remaining'] === 0 ? ' <i class="ti ti-check"></i>' : '' ?></span>
                            </div>
                            <div class="progress progress-sm mt-1"><div class="progress-bar <?= $pct >= 100 ? 'bg-success' : '' ?>" style="width: <?= $pct ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary">No pull list yet. You can still pack anything — or <a href="<?= e(url($base . '/items')) ?>">build the pull list</a> first.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

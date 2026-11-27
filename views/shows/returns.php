<?= app()->view()->partial('shows/_header', compact('show', 'counts', 'showTab')) ?>
<?php
$base = '/shows/' . (int) $show['id'];
$canScan = can('warehouse');
$caseRow = function (array $c, string $hint) use ($base, $canScan): string {
    $html = '<div class="list-group-item d-flex align-items-center gap-2"><i class="ti ti-box text-secondary"></i><div class="flex-fill"><div class="fw-semibold">'
        . e($c['name']) . '</div><div class="text-secondary small">' . e($hint) . '</div></div>';
    if ($canScan && $c['status'] === 'out') {
        $html .= '<form method="post" class="m-0" action="' . e(url($base . '/returns/containers/' . $c['id'])) . '">' . csrf_field()
            . '<button class="btn btn-sm btn-success"><i class="ti ti-check"></i>Back</button></form>';
    } else {
        $html .= status_badge($c['status']);
    }

    return $html . '</div>';
};
?>
<div class="row row-cards">
    <div class="col-lg-8">
        <?php if ($canScan): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <form class="st-scanbox" method="post" action="<?= e(url($base . '/returns')) ?>" data-ajax-scan="#return-result" data-reload="#return-live, #return-cases">
                        <?= csrf_field() ?>
                        <label class="form-label fw-bold" for="return-code"><i class="ti ti-barcode"></i> Scan to check in</label>
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control form-control-lg" id="return-code" name="code" placeholder="Scan a barcode…" autocomplete="off" autofocus data-scan-input></div>
                            <div class="col-6 col-md-3"><input class="form-control form-control-lg" type="number" min="1" name="quantity" value="1" title="Quantity returned (bulk)" aria-label="Quantity returned"></div>
                            <div class="col-6 col-md-3"><input class="form-control form-control-lg" type="number" min="0" name="damaged" value="0" title="How many are damaged" aria-label="Damaged"></div>
                        </div>
                        <div class="text-secondary small mt-2">Quantity and damaged apply to bulk items. Damaged serial units go to the repair list automatically.</div>
                        <div id="return-result" class="mt-3"></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div id="return-live">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-clock-exclamation"></i>Still out with the show</h3><div class="card-actions"><span class="badge bg-blue-lt"><?= (int) $summary['out'] ?> units</span></div></div>
                <?php if ($outstanding): ?>
                    <div class="table-responsive">
                        <table class="table card-table table-vcenter">
                            <thead><tr><th>Item</th><th>Packed in</th><th class="text-end">Out</th><th style="width: 22rem">Check in</th></tr></thead>
                            <tbody>
                            <?php foreach ($outstanding as $o): $open = (int) $o['quantity'] - (int) $o['returned_quantity']; ?>
                                <tr>
                                    <td><?= e($o['item_name']) ?><?= $o['asset_tag'] ? ' <span class="badge bg-secondary-lt st-mono">' . e($o['asset_tag']) . '</span>' : '' ?></td>
                                    <td class="text-secondary small"><?= e($o['container_name'] ?? 'Loose') ?></td>
                                    <td class="text-end fw-semibold"><?= $open ?></td>
                                    <td>
                                        <?php if ($canScan): ?>
                                            <form method="post" action="<?= e(url($base . '/returns/' . $o['id'])) ?>" class="d-flex gap-1">
                                                <?= csrf_field() ?>
                                                <input class="form-control form-control-sm" style="width: 4.2rem" type="number" name="quantity" min="1" max="<?= $open ?>" value="<?= $open ?>" title="Returned" aria-label="Returned"<?= $o['asset_id'] ? ' readonly' : '' ?>>
                                                <input class="form-control form-control-sm" style="width: 4.2rem" type="number" name="damaged" min="0" max="<?= $open ?>" value="0" title="Damaged" aria-label="Damaged">
                                                <input class="form-control form-control-sm" name="note" placeholder="Note" aria-label="Note">
                                                <button class="btn btn-sm btn-success btn-icon" title="Check in" aria-label="Check in"><i class="ti ti-check"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="card-body text-secondary"><?= $summary['sent'] ? '<i class="ti ti-circle-check text-success"></i> Everything is back in the shop.' : 'Nothing has been checked out yet.' ?></div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-history"></i>Checked in</h3><?php if ($summary['damaged']): ?><div class="card-actions"><span class="badge bg-red-lt"><?= (int) $summary['damaged'] ?> damaged</span></div><?php endif; ?></div>
                <?php if ($returned): ?>
                    <table class="table card-table table-sm table-vcenter">
                        <thead><tr><th>Item</th><th class="text-end">Back</th><th class="text-end">Damaged</th><th>By</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($returned as $r): ?>
                            <tr>
                                <td><?= e($r['item_name']) ?><?= $r['asset_tag'] ? ' <span class="text-secondary st-mono small">' . e($r['asset_tag']) . '</span>' : '' ?><?php if ($r['notes']): ?><div class="text-secondary small" style="white-space: pre-wrap"><?= e($r['notes']) ?></div><?php endif; ?></td>
                                <td class="text-end"><?= (int) $r['returned_quantity'] ?></td>
                                <td class="text-end <?= $r['damaged_quantity'] ? 'text-danger fw-bold' : 'text-secondary' ?>"><?= (int) $r['damaged_quantity'] ?></td>
                                <td class="small"><?= e($r['returned_by_name'] ?? '—') ?></td>
                                <td class="small text-secondary"><?= e(fmt_datetime($r['returned_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="card-body text-secondary small">Nothing checked in yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4" id="return-cases">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-truck-return"></i>Back after transport</h3></div>
            <?php if ($summary['containers_after_transport']): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($summary['containers_after_transport'] as $c) { echo $caseRow($c, 'Empty case – should come back once the truck is unloaded.'); } ?>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary small">No cases waiting to come back from transport.</div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-building-warehouse"></i>Staying with the show</h3></div>
            <?php if ($summary['containers_staying']): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($summary['containers_staying'] as $c) { echo $caseRow($c, count($c['contents']) . ' line(s) packed inside – returns at strike.'); } ?>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary small">No cases staying at the venue.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

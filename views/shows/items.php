<?= app()->view()->partial('shows/_header', compact('show', 'counts', 'showTab')) ?>
<?php
$grouped = [];
foreach ($items as $i) { $grouped[$i['category_name'] ?? 'Uncategorised'][] = $i; }
$sectionsList = array_column($sections, 'section');
$bySection = [];
foreach ($lines as $l) { $bySection[$l['section'] ?: 'General'][] = $l; }
?>
<div class="row row-cards">
    <div class="col-lg-8">
        <?php if (! $lines): ?>
            <div class="card"><?= app()->view()->partial('partials/empty', ['icon' => 'list-check', 'title' => 'The pull list is empty', 'text' => 'Add what the show needs. Kits (like a Source Four’s gel frame and safety cable) are added automatically.']) ?></div>
        <?php endif; ?>
        <?php foreach ($bySection as $section => $rows): ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><?= e($section) ?></h3></div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Item</th><th>Shelf</th><th class="text-center" style="width: 9rem">Quantity</th><th>Availability</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $l): $a = $availability[(int) $l['item_id']] ?? null; ?>
                            <tr class="<?= $l['parent_id'] ? 'st-kit-line' : '' ?>">
                                <td>
                                    <a href="<?= e(url('/items/' . $l['item_id'])) ?>" class="<?= $l['parent_id'] ? 'text-secondary' : 'fw-semibold text-reset' ?>"><?= e($l['item_name']) ?></a>
                                    <?= $l['parent_id'] ? '<span class="badge bg-secondary-lt ms-1">kit</span>' : '' ?>
                                    <?php if ($l['notes']): ?><div class="text-secondary small"><?= e($l['notes']) ?></div><?php endif; ?>
                                </td>
                                <td class="text-secondary small"><?= e($l['location_name'] ?? '—') ?></td>
                                <td class="text-center">
                                    <?php if (can('shows.manage') && ! $l['parent_id']): ?>
                                        <form method="post" action="<?= e(url('/shows/' . $show['id'] . '/items/' . $l['id'])) ?>" class="d-flex gap-1 justify-content-center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="section" value="<?= e($l['section']) ?>"><input type="hidden" name="notes" value="<?= e($l['notes']) ?>">
                                            <input class="form-control form-control-sm text-center" style="width: 4.5rem" type="number" min="1" name="quantity" value="<?= (int) $l['quantity'] ?>">
                                            <button class="btn btn-sm btn-icon" title="Save quantity" aria-label="Save quantity"><i class="ti ti-check"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <?= (int) $l['quantity'] ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($a && ! $l['parent_id']): ?>
                                        <?php if ($a['shortage'] > 0): ?>
                                            <span class="badge bg-red-lt"><i class="ti ti-alert-triangle"></i> Short <?= (int) $a['shortage'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-green-lt"><?= (int) ($a['stock'] - $a['other_shows']) ?> free</span>
                                        <?php endif; ?>
                                    <?php elseif ($a && $a['shortage'] > 0): ?>
                                        <span class="badge bg-red-lt">Short <?= (int) $a['shortage'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (can('shows.manage') && ! $l['parent_id']): ?>
                                        <form method="post" action="<?= e(url('/shows/' . $show['id'] . '/items/' . $l['id'] . '/delete')) ?>" data-confirm="Remove <?= e($l['item_name']) ?> and its kit from the pull list?">
                                            <?= csrf_field() ?><button class="btn btn-sm btn-ghost-danger btn-icon" aria-label="Remove"><i class="ti ti-x"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="col-lg-4">
        <?php if (can('shows.manage')): ?>
            <form class="card position-sticky" style="top: 120px" method="post" action="<?= e(url('/shows/' . $show['id'] . '/items')) ?>">
                <?= csrf_field() ?>
                <div class="card-header"><h3 class="card-title"><i class="ti ti-plus"></i>Add to the pull list</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required" for="pick-item">Item</label>
                        <select class="form-select<?= field_error('item_id') ? ' is-invalid' : '' ?>" name="item_id" id="pick-item" required>
                            <option value="">Choose an item…</option>
                            <?php foreach ($grouped as $cat => $list): ?>
                                <optgroup label="<?= e($cat) ?>">
                                    <?php foreach ($list as $i): ?>
                                        <option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?><?= isset($kits[(int) $i['id']]) ? ' (+ kit)' : '' ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <?php if (field_error('item_id')): ?><div class="invalid-feedback d-block"><?= e(field_error('item_id')) ?></div><?php endif; ?>
                    </div>
                    <?= app()->view()->partial('partials/field', ['name' => 'quantity', 'label' => 'How many', 'type' => 'number', 'value' => '1', 'required' => true, 'attrs' => 'min="1"']) ?>
                    <div class="mb-3">
                        <label class="form-label" for="f-section">Section</label>
                        <input class="form-control" name="section" id="f-section" list="sections" placeholder="e.g. FOH, 1st electric, Sound" value="<?= e(old('section')) ?>">
                        <datalist id="sections">
                            <?php foreach (array_unique(array_merge($sectionsList, ['Front of house', '1st electric', '2nd electric', 'Booms', 'Floor', 'Cable', 'Sound', 'Tools'])) as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                    <?= app()->view()->partial('partials/field', ['name' => 'notes', 'label' => 'Notes', 'placeholder' => 'e.g. with R80 gel']) ?>
                    <div class="text-secondary small"><i class="ti ti-info-circle"></i> Items marked “+ kit” bring their accessories with them.</div>
                </div>
                <div class="card-footer"><button class="btn btn-primary w-100"><i class="ti ti-plus"></i>Add</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>

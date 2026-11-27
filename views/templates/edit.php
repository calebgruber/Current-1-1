<?php
$fields = [
    'Paperwork' => [
        'document.title' => 'Title of this paperwork',
        'generated.date' => 'When it was made',
        'generated.by' => 'Who made it',
        'footer' => 'Receipt footer from shop settings',
    ],
    'Shop' => [
        'shop.name' => 'Shop name', 'shop.department' => 'Department', 'shop.institution' => 'College / school',
        'shop.email' => 'Email', 'shop.phone' => 'Phone', 'shop.address' => 'Address',
    ],
    'Show' => [
        'show.reference' => 'Reference, e.g. SHOW-0012', 'show.title' => 'Title', 'show.status' => 'Status',
        'show.venue' => 'Venue', 'show.department' => 'Department', 'show.prep' => 'Prep starts',
        'show.out' => 'Goes out', 'show.opens' => 'Opening night', 'show.closes' => 'Closing night',
        'show.return' => 'Due back', 'show.notes' => 'Notes',
    ],
    'Totals' => [
        'totals.lines' => 'Items on the pull list', 'totals.units' => 'Units on the pull list', 'totals.sent' => 'Units sent',
        'totals.returned' => 'Units returned', 'totals.outstanding' => 'Units still out', 'totals.damaged' => 'Units damaged',
        'totals.containers' => 'Road cases',
    ],
];
$lists = [
    'lines' => ['Pull list (every line)', 'n, item, category, quantity, notes, accessory, location, barcode, tracking'],
    'sections' => ['Pull list grouped by section', 'name, lines (each with the fields above)'],
    'packed' => ['Everything packed or sent', 'item, asset_tag, serial_number, quantity, container, status, returned, damaged, outstanding, notes, returned_at'],
    'sent' => ['Everything that left the shop', 'same fields as packed'],
    'loose' => ['Sent items not in a case', 'same fields as packed'],
    'returned' => ['Items that came back', 'same fields as packed'],
    'outstanding' => ['Items still out', 'same fields as packed'],
    'damaged' => ['Damaged items', 'same fields as packed'],
    'containers' => ['Road cases', 'name, barcode, kind, return_after_transport, comes_back, status, count, contents (item, asset_tag, quantity)'],
    'containers_after_transport' => ['Cases that come back after transport', 'same fields as containers'],
    'contacts' => ['Production team', 'name, role, organization, email, phone'],
];
$showOptions = [];
foreach ($shows as $s) {
    $showOptions[$s['id']] = $s['title'] . ' (' . $s['reference'] . ')';
}
?>
<?= app()->view()->partial('partials/page_header', [
    'pretitle' => '<a href="' . e(url('/templates')) . '"><i class="ti ti-arrow-left"></i> All templates</a>',
    'heading' => $template['name'],
    'subtitle' => 'Last saved ' . e(fmt_datetime($template['updated_at'])) . '. Changes only affect paperwork made from now on.',
    'actions' => '<form method="post" action="' . e(url('/templates/' . $template['id'] . '/reset')) . '" data-confirm="Put this template back to the original? Your changes will be lost.">'
        . csrf_field() . '<button class="btn btn-outline-danger"><i class="ti ti-restore"></i>Reset to default</button></form>',
]) ?>
<div class="row row-cards">
    <div class="col-xl-6">
        <form class="card" method="post" action="<?= e(url('/templates/' . $template['id'])) ?>">
            <?= csrf_field() ?>
            <div class="card-header"><h3 class="card-title"><i class="ti ti-code"></i>Template</h3></div>
            <div class="card-body">
                <div class="row">
                    <?= app()->view()->partial('partials/field', ['name' => 'name', 'label' => 'Name', 'value' => $template['name'], 'required' => true, 'col' => 'col-md-5 mb-3', 'attrs' => 'maxlength="120"']) ?>
                    <?= app()->view()->partial('partials/field', ['name' => 'description', 'label' => 'Description', 'value' => $template['description'] ?? '', 'col' => 'col-md-7 mb-3', 'attrs' => 'maxlength="255"']) ?>
                </div>
                <?php $bodyError = field_error('body'); ?>
                <label class="form-label required" for="f-body">HTML</label>
                <textarea class="form-control st-editor<?= $bodyError ? ' is-invalid' : '' ?>" name="body" id="f-body" spellcheck="false" required
                          data-template-editor data-preview="#tpl-preview" data-preview-url="<?= e(url('/templates/' . $template['id'] . '/preview')) ?>"><?= e(old('body', $template['body'])) ?></textarea>
                <?php if ($bodyError): ?><div class="invalid-feedback d-block"><?= e($bodyError) ?></div><?php endif; ?>
                <small class="form-hint">Use <code>{{ show.title }}</code> to insert a value. Scripts are not allowed and will not run.</small>
            </div>
            <div class="card-footer d-flex align-items-center gap-2">
                <span data-preview-status class="text-secondary small"></span>
                <button class="btn btn-primary ms-auto"><i class="ti ti-device-floppy"></i>Save template</button>
            </div>
        </form>
    </div>
    <div class="col-xl-6">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-eye"></i>Live preview</h3>
                <div class="card-actions">
                    <select class="form-select form-select-sm" data-preview-show aria-label="Preview with show">
                        <?php if (! $showOptions): ?><option value="">Sample show</option><?php endif; ?>
                        <?php foreach ($showOptions as $value => $label): ?><option value="<?= (int) $value ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="card-body p-2">
                <?php /* No allow-scripts: previewed template HTML can never run code. */ ?>
                <iframe id="tpl-preview" class="st-doc-frame" title="Template preview" sandbox="allow-same-origin"></iframe>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <a class="card-title text-reset" data-bs-toggle="collapse" href="#tpl-help" role="button" aria-expanded="false" aria-controls="tpl-help">
                    <i class="ti ti-help-circle"></i>Fields you can use <i class="ti ti-chevron-down ms-1"></i>
                </a>
            </div>
            <div class="collapse" id="tpl-help">
                <div class="card-body">
                    <p class="mb-2">Values are always made safe for HTML. Lists repeat what is between the tags once for every entry:</p>
                    <pre class="mb-3"><code>{{#lines}}
  &lt;tr&gt;&lt;td&gt;{{ quantity }}&lt;/td&gt;&lt;td&gt;{{ item }}&lt;/td&gt;&lt;/tr&gt;
{{/lines}}
{{^lines}}&lt;p&gt;Nothing on the pull list yet.&lt;/p&gt;{{/lines}}</code></pre>
                    <p class="text-secondary small"><code>{{#name}}…{{/name}}</code> shows the inside for each entry (or once, if the value is yes / not empty). <code>{{^name}}…{{/name}}</code> shows it only when the list is empty or the value is no.</p>
                    <?php foreach ($fields as $group => $items): ?>
                        <h4 class="mt-3 mb-1"><?= e($group) ?></h4>
                        <table class="table table-sm mb-0">
                            <?php foreach ($items as $key => $label): ?>
                                <tr><td class="w-50"><code>{{ <?= e($key) ?> }}</code></td><td class="text-secondary"><?= e($label) ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endforeach; ?>
                    <h4 class="mt-3 mb-1">Lists</h4>
                    <table class="table table-sm mb-0">
                        <?php foreach ($lists as $key => [$label, $inside]): ?>
                            <tr><td class="w-50"><code>{{#<?= e($key) ?>}}</code><div class="text-secondary small"><?= e($label) ?></div></td><td class="text-secondary small"><?= e($inside) ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

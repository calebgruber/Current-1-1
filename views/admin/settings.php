<?php
$f = fn (array $o) => app()->view()->partial('partials/field', $o);
$field = fn (string $key, array $extra = []) => $f($extra + [
    'name' => $key,
    'label' => $fields[$key][0],
    'hint' => $fields[$key][1],
    'value' => $values[$key] ?? '',
]);
$months = [];
for ($i = 1; $i <= 12; $i++) {
    $months[$i] = $i . ' month' . ($i === 1 ? '' : 's');
}
?>
<?= app()->view()->partial('partials/page_header', ['heading' => 'Shop settings', 'subtitle' => 'These details appear across the app and on printed paperwork.']) ?>
<form method="post" action="<?= e(url('/admin/settings')) ?>">
    <?= csrf_field() ?>
    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-building-store"></i>About the shop</h3></div>
                <div class="card-body">
                    <?= $field('shop_name', ['required' => true]) ?>
                    <?= $field('shop_department') ?>
                    <?= $field('institution') ?>
                    <?= $field('shop_email', ['type' => 'email']) ?>
                    <?= $field('shop_phone') ?>
                    <?= $field('shop_address', ['type' => 'textarea']) ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-adjustments"></i>How things work</h3></div>
                <div class="card-body">
                    <?= $field('scan_sounds', ['type' => 'checkbox']) ?>
                    <?= $field('calendar_months', ['type' => 'select', 'options' => $months]) ?>
                    <?= $field('receipt_footer', ['type' => 'textarea', 'rows' => 4]) ?>
                </div>
            </div>
        </div>
        <div class="col-12 text-end">
            <button class="btn btn-primary"><i class="ti ti-device-floppy"></i>Save settings</button>
        </div>
    </div>
</form>

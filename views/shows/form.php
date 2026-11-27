<?php
$f = fn (array $o) => app()->view()->partial('partials/field', $o);
$editing = isset($show['id']);
$editableStatus = in_array($show['status'], ['draft', 'confirmed', 'prepping'], true);
?>
<?= app()->view()->partial('partials/page_header', ['heading' => $editing ? 'Edit ' . $show['title'] : 'New show', 'pretitle' => $editing ? e($show['reference']) : 'Shows']) ?>
<form method="post" action="<?= e(url($editing ? '/shows/' . $show['id'] : '/shows')) ?>">
    <?= csrf_field() ?>
    <div class="row row-cards">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-masks-theater"></i>The show</h3></div>
                <div class="card-body">
                    <?= $f(['name' => 'title', 'label' => 'Show title', 'value' => $show['title'], 'required' => true, 'placeholder' => 'e.g. Into the Woods', 'attrs' => 'autofocus']) ?>
                    <div class="row">
                        <?= $f(['name' => 'venue', 'label' => 'Venue', 'value' => $show['venue'], 'placeholder' => 'e.g. Main Stage Theatre', 'col' => 'col-md-7 mb-3']) ?>
                        <?= $f(['name' => 'department', 'label' => 'Department / company', 'value' => $show['department'], 'placeholder' => 'Theatre, Dance, Music…', 'col' => 'col-md-5 mb-3']) ?>
                    </div>
                    <?php if ($editableStatus): ?>
                        <?= $f(['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $show['status'], 'options' => ['draft' => 'Draft – still planning', 'confirmed' => 'Confirmed', 'prepping' => 'Prepping in the shop']]) ?>
                    <?php endif; ?>
                    <?= $f(['name' => 'notes', 'label' => 'Notes for the shop', 'type' => 'textarea', 'rows' => 5, 'value' => $show['notes'], 'placeholder' => 'Load-in details, special requests, who to call…']) ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-calendar-time"></i>Dates</h3></div>
                <div class="card-body">
                    <?= $f(['name' => 'prep_at', 'label' => 'Prep starts in the shop', 'type' => 'datetime-local', 'value' => $show['prep_at']]) ?>
                    <?= $f(['name' => 'out_at', 'label' => 'Equipment goes out (load-in)', 'type' => 'datetime-local', 'value' => $show['out_at'], 'required' => true]) ?>
                    <div class="row">
                        <?= $f(['name' => 'opens_on', 'label' => 'Opening night', 'type' => 'date', 'value' => $show['opens_on'], 'col' => 'col-6 mb-3']) ?>
                        <?= $f(['name' => 'closes_on', 'label' => 'Closing night', 'type' => 'date', 'value' => $show['closes_on'], 'col' => 'col-6 mb-3']) ?>
                    </div>
                    <?= $f(['name' => 'return_at', 'label' => 'Equipment due back (after strike)', 'type' => 'datetime-local', 'value' => $show['return_at'], 'required' => true, 'hint' => 'Equipment is booked from “goes out” until this time.']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3">
        <a class="btn" href="<?= e(url($editing ? '/shows/' . $show['id'] : '/shows')) ?>">Cancel</a>
        <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy"></i><?= $editing ? 'Save changes' : 'Create show' ?></button>
    </div>
</form>

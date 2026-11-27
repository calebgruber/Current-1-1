<?php $f = fn (array $o) => app()->view()->partial('partials/field', $o); ?>
<?= app()->view()->partial('partials/page_header', ['heading' => 'New contact', 'pretitle' => '<a href="' . e(url('/contacts')) . '">Contacts</a>']) ?>
<div class="row row-cards">
    <div class="col-lg-8">
        <form class="card" method="post" action="<?= e(url('/contacts')) ?>">
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="row">
                    <?= $f(['name' => 'name', 'label' => 'Name', 'value' => $contact['name'], 'required' => true, 'col' => 'col-md-6 mb-3', 'attrs' => 'autofocus']) ?>
                    <?= $f(['name' => 'role', 'label' => 'Role', 'value' => $contact['role'], 'placeholder' => 'e.g. Stage manager', 'col' => 'col-md-6 mb-3']) ?>
                    <?= $f(['name' => 'organization', 'label' => 'Organization', 'value' => $contact['organization'], 'placeholder' => 'e.g. Drama Society', 'col' => 'col-md-6 mb-3']) ?>
                    <?= $f(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $contact['email'], 'col' => 'col-md-6 mb-3']) ?>
                    <?= $f(['name' => 'phone', 'label' => 'Phone', 'value' => $contact['phone'], 'col' => 'col-md-6 mb-3']) ?>
                </div>
                <?= $f(['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'value' => $contact['notes']]) ?>
            </div>
            <div class="card-footer d-flex">
                <a class="btn btn-link" href="<?= e(url('/contacts')) ?>">Cancel</a>
                <button class="btn btn-primary ms-auto"><i class="ti ti-device-floppy"></i>Save contact</button>
            </div>
        </form>
    </div>
</div>

<?php $f = fn (array $o) => app()->view()->partial('partials/field', $o); ?>
<?= app()->view()->partial('partials/page_header', ['heading' => 'My account', 'pretitle' => e(\App\Core\Auth::ROLES[$me['role']] ?? $me['role'])]) ?>
<div class="row row-cards">
    <div class="col-lg-6">
        <form class="card" method="post" action="<?= e(url('/account')) ?>">
            <?= csrf_field() ?>
            <div class="card-header"><h3 class="card-title"><i class="ti ti-user"></i>Profile & preferences</h3></div>
            <div class="card-body">
                <?= $f(['name' => 'name', 'label' => 'Name', 'value' => $me['name'], 'required' => true]) ?>
                <?= $f(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $me['email'], 'required' => true]) ?>
                <?= $f(['name' => 'theme', 'label' => 'Appearance', 'type' => 'select', 'value' => $me['theme'], 'options' => ['auto' => 'Match my device', 'light' => 'Light', 'dark' => 'Dark']]) ?>
                <?= $f(['name' => 'sound_muted', 'label' => 'Mute scan sounds', 'type' => 'checkbox', 'value' => $me['sound_muted'], 'hint' => 'You can also use the speaker button in the top bar.']) ?>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary" onclick="localStorage.removeItem('st-theme')"><i class="ti ti-device-floppy"></i>Save</button></div>
        </form>
    </div>
    <div class="col-lg-6">
        <form class="card" method="post" action="<?= e(url('/account/password')) ?>">
            <?= csrf_field() ?>
            <div class="card-header"><h3 class="card-title"><i class="ti ti-lock"></i>Change password</h3></div>
            <div class="card-body">
                <?= $f(['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
                <?= $f(['name' => 'password', 'label' => 'New password', 'type' => 'password', 'required' => true, 'hint' => 'At least 8 characters.', 'attrs' => 'autocomplete="new-password"']) ?>
                <?= $f(['name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'required' => true, 'attrs' => 'autocomplete="new-password"']) ?>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-key"></i>Change password</button></div>
        </form>
    </div>
</div>

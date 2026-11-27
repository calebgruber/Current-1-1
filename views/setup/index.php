<?php $f = fn (array $o) => app()->view()->partial('partials/field', $o); ?>
<div class="card card-md">
    <div class="card-body">
        <h2 class="h2 text-center mb-2">Set up your shop</h2>
        <p class="text-secondary text-center mb-4">This takes about a minute. Everything happens here — no command line needed.</p>

        <div class="mb-4">
            <div class="st-section-label mb-2">Server check</div>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($checks as [$label, $ok, $detail]): ?>
                    <li class="d-flex align-items-center gap-2 mb-1">
                        <i class="ti ti-<?= $ok ? 'circle-check text-success' : 'circle-x text-danger' ?>"></i>
                        <span><?= e($label) ?></span><span class="text-secondary ms-auto"><?= e($detail) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <form method="post" action="<?= e(url('/setup')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <?php if ($needsDatabase): ?>
                <div class="st-section-label mb-2">1 · Database</div>
                <div class="form-selectgroup form-selectgroup-boxes d-flex mb-3">
                    <label class="form-selectgroup-item flex-fill">
                        <input type="radio" name="driver" value="sqlite" class="form-selectgroup-input" <?= old('driver', 'sqlite') === 'sqlite' ? 'checked' : '' ?> onchange="document.getElementById('mysql-fields').hidden = true">
                        <span class="form-selectgroup-label d-flex align-items-center p-3">
                            <span class="me-3"><span class="form-selectgroup-check"></span></span>
                            <span><strong>Simple (SQLite)</strong><span class="d-block text-secondary small">A single file in storage/. Best for one shop.</span></span>
                        </span>
                    </label>
                    <label class="form-selectgroup-item flex-fill">
                        <input type="radio" name="driver" value="mysql" class="form-selectgroup-input" <?= old('driver') === 'mysql' ? 'checked' : '' ?> onchange="document.getElementById('mysql-fields').hidden = false">
                        <span class="form-selectgroup-label d-flex align-items-center p-3">
                            <span class="me-3"><span class="form-selectgroup-check"></span></span>
                            <span><strong>MySQL</strong><span class="d-block text-secondary small">Create the database in cPanel first.</span></span>
                        </span>
                    </label>
                </div>
                <div id="mysql-fields" class="row" <?= old('driver') === 'mysql' ? '' : 'hidden' ?>>
                    <?= $f(['name' => 'db_host', 'label' => 'Host', 'value' => 'localhost', 'col' => 'col-8 mb-3']) ?>
                    <?= $f(['name' => 'db_port', 'label' => 'Port', 'value' => '3306', 'type' => 'number', 'col' => 'col-4 mb-3']) ?>
                    <?= $f(['name' => 'db_database', 'label' => 'Database name', 'col' => 'col-12 mb-3']) ?>
                    <?= $f(['name' => 'db_username', 'label' => 'Database user', 'col' => 'col-6 mb-3']) ?>
                    <?= $f(['name' => 'db_password', 'label' => 'Database password', 'type' => 'password', 'col' => 'col-6 mb-3']) ?>
                </div>
            <?php endif; ?>

            <div class="st-section-label mb-2 mt-2"><?= $needsDatabase ? '2' : '1' ?> · Your shop</div>
            <div class="row">
                <?= $f(['name' => 'shop_name', 'label' => 'Shop name', 'value' => 'Lighting & Sound Shop', 'required' => true, 'col' => 'col-md-6 mb-3']) ?>
                <?= $f(['name' => 'institution', 'label' => 'College / department', 'placeholder' => 'e.g. Riverside College Theatre', 'col' => 'col-md-6 mb-3']) ?>
            </div>

            <div class="st-section-label mb-2 mt-2"><?= $needsDatabase ? '3' : '2' ?> · Shop manager account</div>
            <?= $f(['name' => 'name', 'label' => 'Your name', 'required' => true]) ?>
            <?= $f(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]) ?>
            <div class="row">
                <?= $f(['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'hint' => 'At least 8 characters.', 'col' => 'col-md-6 mb-3']) ?>
                <?= $f(['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'required' => true, 'col' => 'col-md-6 mb-3']) ?>
            </div>
            <?= $f(['name' => 'demo', 'label' => 'Load example equipment and shows', 'type' => 'checkbox', 'value' => '1', 'hint' => 'Source Fours with gel frames and safety cables, mics, cable trunks and a sample show. Handy for training students.']) ?>

            <button type="submit" class="btn btn-primary w-100 btn-lg mt-2"><i class="ti ti-rocket"></i>Set up StageTrack</button>
        </form>
    </div>
</div>

<?php
use App\Core\Auth;

$f = fn (array $o) => app()->view()->partial('partials/field', $o);
$roleColors = ['admin' => 'red', 'staff' => 'blue', 'student' => 'green'];
?>
<?= app()->view()->partial('partials/page_header', ['heading' => 'Users', 'subtitle' => 'Everyone who can sign in to the shop.']) ?>
<div class="row row-cards">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last signed in</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="<?= $u['active'] ? '' : 'text-secondary' ?>">
                            <td>
                                <div class="fw-semibold"><?= e($u['name']) ?><?= (int) $u['id'] === $meId ? ' <span class="badge bg-secondary-lt">You</span>' : '' ?></div>
                                <div class="text-secondary small"><?= e($u['email']) ?></div>
                            </td>
                            <td><span class="badge bg-<?= e($roleColors[$u['role']] ?? 'secondary') ?>-lt"><?= e(Auth::ROLES[$u['role']] ?? $u['role']) ?></span></td>
                            <td><?= $u['active'] ? '<span class="badge bg-green-lt">Active</span>' : '<span class="badge bg-secondary-lt">Turned off</span>' ?></td>
                            <td><?= e($u['last_login_at'] ? fmt_datetime($u['last_login_at']) : 'Never') ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#edit-user-<?= (int) $u['id'] ?>"><i class="ti ti-pencil"></i>Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <form class="card mb-3" method="post" action="<?= e(url('/admin/users')) ?>">
            <?= csrf_field() ?>
            <div class="card-header"><h3 class="card-title"><i class="ti ti-user-plus"></i>Add user</h3></div>
            <div class="card-body">
                <?= $f(['name' => 'name', 'label' => 'Name', 'required' => true]) ?>
                <?= $f(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'hint' => 'They sign in with this.']) ?>
                <?= $f(['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => Auth::ROLES, 'value' => 'student', 'required' => true]) ?>
                <?= $f(['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'hint' => 'At least 8 characters. They can change it under My account.', 'attrs' => 'autocomplete="new-password" minlength="8"']) ?>
            </div>
            <div class="card-footer"><button class="btn btn-primary w-100"><i class="ti ti-plus"></i>Add user</button></div>
        </form>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-help-circle"></i>What can each role do?</h3></div>
            <div class="card-body">
                <p><span class="badge bg-red-lt"><?= e(Auth::ROLES['admin']) ?></span><br>Everything, including users, settings and the database.</p>
                <p><span class="badge bg-blue-lt"><?= e(Auth::ROLES['staff']) ?></span><br>Manage inventory, shows, paperwork templates and contacts.</p>
                <p class="mb-0"><span class="badge bg-green-lt"><?= e(Auth::ROLES['student']) ?></span><br>Scan, prep and return gear, send messages and make paperwork.</p>
            </div>
        </div>
    </div>
</div>

<?php foreach ($users as $u): $uid = (int) $u['id']; $isMe = $uid === $meId; ?>
    <div class="modal modal-blur fade" id="edit-user-<?= $uid ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="<?= e(url('/admin/users/' . $uid)) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit <?= e($u['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required" for="u<?= $uid ?>-name">Name</label>
                        <input class="form-control" id="u<?= $uid ?>-name" name="name" value="<?= e($u['name']) ?>" required maxlength="120">
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="u<?= $uid ?>-email">Email</label>
                        <input class="form-control" type="email" id="u<?= $uid ?>-email" name="email" value="<?= e($u['email']) ?>" required maxlength="190">
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="u<?= $uid ?>-role">Role</label>
                        <select class="form-select" id="u<?= $uid ?>-role" name="role">
                            <?php foreach (Auth::ROLES as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $value === $u['role'] ? 'selected' : '' ?> <?= $isMe && $value !== 'admin' ? 'disabled' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isMe): ?><small class="form-hint">You can’t remove your own admin role.</small><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input type="hidden" name="active" value="<?= $isMe ? '1' : '0' ?>">
                            <input class="form-check-input" type="checkbox" name="active" value="1" <?= $u['active'] ? 'checked' : '' ?> <?= $isMe ? 'disabled' : '' ?>>
                            <span class="form-check-label">Can sign in</span>
                            <span class="form-check-description"><?= $isMe ? 'You can’t turn off your own account.' : 'Turn this off when someone leaves. Their history is kept.' ?></span>
                        </label>
                    </div>
                    <div>
                        <label class="form-label" for="u<?= $uid ?>-password">New password</label>
                        <input class="form-control" type="password" id="u<?= $uid ?>-password" name="password" autocomplete="new-password" minlength="8">
                        <small class="form-hint">Leave blank to keep their current password.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary ms-auto"><i class="ti ti-device-floppy"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

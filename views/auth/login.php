<div class="card card-md">
    <div class="card-body">
        <h2 class="h2 text-center mb-4">Sign in to the shop</h2>
        <form method="post" action="<?= e(url('/login')) ?>" autocomplete="on">
            <?= csrf_field() ?>
            <?= app()->view()->partial('partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => 'autofocus autocomplete="username"']) ?>
            <?= app()->view()->partial('partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
            <button type="submit" class="btn btn-primary w-100"><i class="ti ti-login"></i>Sign in</button>
        </form>
    </div>
</div>
<p class="text-center text-secondary mt-3 small">Forgot your password? Ask the shop manager to reset it.</p>

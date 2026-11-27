<?= app()->view()->partial('partials/page_header', ['heading' => 'Database', 'subtitle' => 'Run updates and maintenance here — no command line needed.']) ?>
<div class="row row-cards">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-database-cog"></i>Migrations</h3>
                <div class="card-actions">
                    <form method="post" action="<?= e(url('/admin/database/migrate')) ?>" data-confirm="Run <?= (int) $pending ?> pending migration<?= $pending === 1 ? '' : 's' ?>? Back up the database first if it holds real data.">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary" <?= $pending ? '' : 'disabled' ?>><i class="ti ti-player-play"></i><?= $pending ? 'Run ' . (int) $pending . ' pending migration' . ($pending === 1 ? '' : 's') : 'Up to date' ?></button>
                    </form>
                </div>
            </div>
            <div class="card-body border-bottom py-2 text-secondary small">
                Migrations update the database structure after you install a new version of StageTrack. Run any that show as pending.
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Migration</th><th>Status</th><th>Batch</th><th>Ran</th></tr></thead>
                    <tbody>
                    <?php foreach ($migrations as $m): ?>
                        <tr>
                            <td class="st-mono"><?= e($m['name']) ?></td>
                            <td><?= $m['ran'] ? '<span class="badge bg-green-lt">Ran</span>' : '<span class="badge bg-orange-lt">Pending</span>' ?></td>
                            <td><?= $m['batch'] !== null ? (int) $m['batch'] : '—' ?></td>
                            <td><?= e(fmt_datetime($m['ran_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-server"></i>Connection</h3></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Type</dt><dd class="col-7"><?= $driver === 'mysql' ? 'MySQL / MariaDB' : 'SQLite' ?></dd>
                    <?php if ($file !== null): ?><dt class="col-5">File</dt><dd class="col-7 st-mono"><?= e($file) ?></dd><?php endif; ?>
                    <?php foreach ($counts as $label => $count): ?>
                        <dt class="col-5"><?= e($label) ?></dt><dd class="col-7"><?= $count === null ? '—' : number_format($count) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-tool"></i>Maintenance</h3></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('/admin/database/templates')) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <button class="btn w-100"><i class="ti ti-file-plus"></i>Reinstall missing paperwork templates</button>
                    <small class="form-hint">Puts back any built-in template that was deleted. Your edited templates are left alone.</small>
                </form>
                <form method="post" action="<?= e(url('/admin/database/demo')) ?>" data-confirm="Load demo equipment, containers and shows? Best for trying StageTrack out — it adds sample records to your real data.">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-warning w-100"><i class="ti ti-sparkles"></i>Load demo data</button>
                    <small class="form-hint">Sample gear and shows to explore with. It can only be loaded once.</small>
                </form>
            </div>
        </div>
    </div>
</div>

<?= app()->view()->partial('partials/page_header', ['heading' => $q !== '' ? 'Results for “' . $q . '”' : 'Search']) ?>
<div class="card mb-3">
    <div class="card-body">
        <form action="<?= e(url('/search')) ?>" method="get" data-scan-form>
            <div class="input-group input-group-lg">
                <span class="input-group-text"><i class="ti ti-scan"></i></span>
                <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Scan a barcode or search items, units, shows, cases, locations and contacts" autofocus autocomplete="off">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>
    </div>
</div>
<?php if ($q === ''): ?>
    <?= app()->view()->partial('partials/empty', ['icon' => 'search', 'title' => 'Search everything in the shop', 'text' => 'Type a name or scan any barcode.']) ?>
<?php elseif (! $results): ?>
    <?= app()->view()->partial('partials/empty', ['icon' => 'mood-confuzed', 'title' => 'Nothing matched “' . $q . '”', 'text' => 'Check the spelling or try part of the name.']) ?>
<?php else: ?>
    <div class="row row-cards">
        <?php foreach ($results as $group => $rows): ?>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title"><?= e($group) ?> <span class="badge bg-secondary-lt ms-1"><?= count($rows) ?></span></h3></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($rows as $r): ?>
                            <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= e(url($r['url'])) ?>">
                                <span class="st-thumb"><i class="ti ti-<?= e($r['icon']) ?>"></i></span>
                                <div class="flex-fill"><div class="fw-semibold"><?= e($r['title']) ?></div><div class="text-secondary small"><?= e($r['meta']) ?></div></div>
                                <?= isset($r['status']) ? status_badge($r['status']) : '' ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

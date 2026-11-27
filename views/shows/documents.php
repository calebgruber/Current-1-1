<?= app()->view()->partial('shows/_header', compact('show', 'counts', 'showTab')) ?>
<div class="row row-cards">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-files"></i>Paperwork for this show</h3></div>
            <?php if ($documents): ?>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Title</th><th>Made</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($documents as $d): ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="<?= e(url('/documents/' . $d['id'])) ?>"><?= e($d['title']) ?></a>
                                    <?php if ($d['shared']): ?><span class="badge bg-green-lt ms-1">Shared</span><?php endif; ?>
                                </td>
                                <td class="text-secondary small text-nowrap"><?= e(fmt_datetime($d['created_at'])) ?><?= $d['user_name'] ? '<div>by ' . e($d['user_name']) . '</div>' : '' ?></td>
                                <td class="text-end">
                                    <div class="btn-list flex-nowrap justify-content-end">
                                        <a class="btn btn-sm" href="<?= e(url('/documents/' . $d['id'])) ?>"><i class="ti ti-eye"></i>View</a>
                                        <a class="btn btn-sm" href="<?= e(url('/documents/' . $d['id'] . '/print')) ?>" target="_blank" rel="noopener"><i class="ti ti-printer"></i>Print</a>
                                        <?php if (can('documents.generate')): ?>
                                            <form method="post" action="<?= e(url('/documents/' . $d['id'] . '/share')) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm<?= $d['shared'] ? ' btn-outline-secondary' : '' ?>" title="<?= $d['shared'] ? 'Hide from the show portal' : 'Show on the show portal' ?>">
                                                    <i class="ti ti-<?= $d['shared'] ? 'share-off' : 'share' ?>"></i><?= $d['shared'] ? 'Unshare' : 'Share' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (can('delete')): ?>
                                            <form method="post" action="<?= e(url('/documents/' . $d['id'] . '/delete')) ?>" data-confirm="Delete “<?= e($d['title']) ?>”? This cannot be undone.">
                                                <?= csrf_field() ?><button class="btn btn-sm btn-ghost-danger btn-icon" aria-label="Delete"><i class="ti ti-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= app()->view()->partial('partials/empty', ['icon' => 'file-text', 'title' => 'No paperwork yet', 'text' => 'Pick a type of paperwork on the right to make it from this show’s pull list.']) ?>
            <?php endif; ?>
            <div class="card-footer text-secondary small">
                <i class="ti ti-share"></i> Shared paperwork appears on the show portal. Find the link on the <a href="<?= e(url('/shows/' . $show['id'] . '/messages')) ?>">Messages</a> tab.
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-file-plus"></i>Create paperwork</h3></div>
            <?php if ($templates): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($templates as $t): ?>
                        <div class="list-group-item">
                            <div class="row align-items-center g-2">
                                <div class="col">
                                    <div class="fw-semibold"><?= e($t['name']) ?></div>
                                    <div class="text-secondary small"><?= e($t['description'] ?? '') ?></div>
                                </div>
                                <?php if (can('documents.generate')): ?>
                                    <div class="col-auto">
                                        <form method="post" action="<?= e(url('/shows/' . $show['id'] . '/documents')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="template_key" value="<?= e($t['template_key']) ?>">
                                            <button class="btn btn-sm btn-primary"><i class="ti ti-plus"></i>Create</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= app()->view()->partial('partials/empty', [
                    'icon' => 'template',
                    'title' => 'No templates installed',
                    'text' => 'An admin can reinstall the built-in paperwork from Admin → Database.',
                ]) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

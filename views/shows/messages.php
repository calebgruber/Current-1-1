<?= app()->view()->partial('shows/_header', compact('show', 'counts', 'showTab')) ?>
<?php $portal = url('/portal/' . $show['share_token']); ?>
<div class="row row-cards">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-messages"></i>Conversation with the production</h3></div>
            <div class="card-body">
                <?php if ($messages): ?>
                    <div class="st-chat">
                        <?php foreach ($messages as $m): ?>
                            <div class="st-msg <?= e($m['direction']) ?>">
                                <div class="st-msg-meta">
                                    <?= e($m['author_name']) ?> · <?= e(fmt_datetime($m['created_at'])) ?>
                                    <?= $m['direction'] === 'internal' ? ' · <strong>Internal note</strong>' : '' ?>
                                </div>
                                <div class="st-msg-body"><?= e($m['body']) ?></div>
                                <?php if ($m['document_id']): ?>
                                    <a class="btn btn-sm mt-2" href="<?= e(url('/documents/' . $m['document_id'])) ?>"><i class="ti ti-file-text"></i><?= e($m['document_title'] ?? 'Document') ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?= app()->view()->partial('partials/empty', ['icon' => 'message-circle', 'title' => 'No messages yet', 'text' => 'Send the production team the portal link so they can see paperwork and reply.']) ?>
                <?php endif; ?>
            </div>
            <?php if (can('messages')): ?>
                <form class="card-footer" method="post" action="<?= e(url('/shows/' . $show['id'] . '/messages')) ?>">
                    <?= csrf_field() ?>
                    <?= app()->view()->partial('partials/field', ['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'placeholder' => 'Write to the production team…']) ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label" for="f-document_id">Attach paperwork</label>
                            <select class="form-select" name="document_id" id="f-document_id">
                                <option value="">No attachment</option>
                                <?php foreach ($documents as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['title']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="f-direction">Who can see it</label>
                            <select class="form-select" name="direction" id="f-direction">
                                <option value="shop">Send to the show</option>
                                <option value="internal">Internal note (shop only)</option>
                            </select>
                        </div>
                        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="ti ti-send"></i>Send</button></div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-link"></i>Show portal</h3></div>
            <div class="card-body">
                <p class="text-secondary">Share this private link with the production team. They can read your messages, reply, and open paperwork you share — no account needed.</p>
                <div class="input-group mb-2">
                    <input class="form-control st-mono small" id="portal-link" readonly value="<?= e($portal) ?>">
                    <button class="btn" type="button" onclick="var i=document.getElementById('portal-link');navigator.clipboard.writeText(new URL(i.value, location.href).href);this.textContent='Copied'">Copy</button>
                </div>
                <a class="btn btn-sm" href="<?= e($portal) ?>" target="_blank" rel="noopener"><i class="ti ti-external-link"></i>Open portal</a>
            </div>
            <?php if (can('shows.manage')): ?>
                <div class="card-footer">
                    <form method="post" action="<?= e(url('/shows/' . $show['id'] . '/share')) ?>" data-confirm="Make a new link? The old link will stop working.">
                        <?= csrf_field() ?><button class="btn btn-sm btn-ghost-danger"><i class="ti ti-refresh"></i>Make a new link</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$shopEmail = setting('shop_email');
$shopPhone = setting('shop_phone');
?>
<div class="row row-cards" style="--module-color: #ae3ec9">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col">
                        <div class="text-secondary small st-mono"><?= e($show['reference']) ?></div>
                        <h1 class="h2 mb-1 d-flex align-items-center gap-2"><?= e($show['title']) ?> <?= status_badge($show['status']) ?></h1>
                        <div class="text-secondary d-flex flex-wrap gap-3">
                            <?php if ($show['venue']): ?><span><i class="ti ti-map-pin"></i> <?= e($show['venue']) ?></span><?php endif; ?>
                            <span><i class="ti ti-truck"></i> Equipment out <?= e(fmt_datetime($show['out_at'])) ?></span>
                            <?php if ($show['opens_on']): ?><span><i class="ti ti-star"></i> Opens <?= e(fmt_date($show['opens_on'])) ?></span><?php endif; ?>
                            <?php if ($show['closes_on']): ?><span><i class="ti ti-flag"></i> Closes <?= e(fmt_date($show['closes_on'])) ?></span><?php endif; ?>
                            <span><i class="ti ti-arrow-back-up"></i> Due back <?= e(fmt_datetime($show['return_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-messages me-1"></i>Messages with the shop</h3></div>
            <div class="card-body">
                <?php if ($messages): ?>
                    <div class="st-chat">
                        <?php foreach ($messages as $m): ?>
                            <?php /* From the portal's point of view, the show's own messages sit on the right. */ ?>
                            <div class="st-msg <?= $m['direction'] === 'show' ? 'shop' : 'show' ?>">
                                <div class="st-msg-meta"><?= e($m['author_name'] ?: ($m['direction'] === 'shop' ? 'Shop' : 'Production')) ?> · <?= e(fmt_datetime($m['created_at'])) ?></div>
                                <div class="st-msg-body"><?= e($m['body']) ?></div>
                                <?php if ($m['document_id'] && $m['document_shared']): ?>
                                    <a class="btn btn-sm mt-2" href="<?= e(url('/portal/' . $token . '/documents/' . $m['document_id'])) ?>" target="_blank" rel="noopener"><i class="ti ti-file-text"></i><?= e($m['document_title']) ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?= app()->view()->partial('partials/empty', ['icon' => 'message-circle', 'title' => 'No messages yet', 'text' => 'Have a question for the shop? Send a message below.']) ?>
                <?php endif; ?>
            </div>
            <form class="card-footer" method="post" action="<?= e(url('/portal/' . $token . '/messages')) ?>">
                <?= csrf_field() ?>
                <div class="row">
                    <?= app()->view()->partial('partials/field', ['name' => 'name', 'label' => 'Your name', 'value' => $name, 'required' => true, 'col' => 'col-md-6 mb-3', 'placeholder' => 'e.g. Sam (Stage manager)', 'attrs' => 'maxlength="120" autocomplete="name"']) ?>
                </div>
                <?= app()->view()->partial('partials/field', ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'placeholder' => 'Write to the shop…', 'attrs' => 'maxlength="4000"']) ?>
                <div class="d-flex align-items-center">
                    <span class="text-secondary small">The shop team will see this right away.</span>
                    <button class="btn btn-primary ms-auto"><i class="ti ti-send"></i>Send</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-file-text me-1"></i>Paperwork</h3></div>
            <?php if ($documents): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($documents as $d): ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(url('/portal/' . $token . '/documents/' . $d['id'])) ?>" target="_blank" rel="noopener">
                            <div class="fw-semibold"><i class="ti ti-file-text text-secondary me-1"></i><?= e($d['title']) ?></div>
                            <div class="text-secondary small"><?= e(fmt_datetime($d['created_at'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-body text-secondary">The shop has not shared any paperwork yet.</div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-building-store me-1"></i>Contact the shop</h3></div>
            <div class="card-body">
                <div class="fw-semibold mb-2"><?= e(setting('shop_name', 'The shop')) ?></div>
                <?php if ($shopEmail): ?><div class="mb-1"><i class="ti ti-mail text-secondary"></i> <a href="mailto:<?= e($shopEmail) ?>"><?= e($shopEmail) ?></a></div><?php endif; ?>
                <?php if ($shopPhone): ?><div class="mb-1"><i class="ti ti-phone text-secondary"></i> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $shopPhone)) ?>"><?= e($shopPhone) ?></a></div><?php endif; ?>
                <p class="text-secondary small mt-3 mb-0">This page is private to your production. Please don’t share the link outside your team.</p>
            </div>
        </div>
    </div>
</div>

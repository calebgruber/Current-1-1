<?php
/** heading, pretitle, actions (html), subtitle */
?>
<div class="page-header st-page-header">
    <div class="row g-2 align-items-center">
        <div class="col">
            <?php if (! empty($pretitle)): ?><div class="page-pretitle"><?= $pretitle ?></div><?php endif; ?>
            <h2 class="page-title"><?= e($heading) ?></h2>
            <?php if (! empty($subtitle)): ?><div class="text-secondary mt-1"><?= $subtitle ?></div><?php endif; ?>
        </div>
        <?php if (! empty($actions)): ?>
            <div class="col-auto ms-auto d-print-none"><div class="btn-list"><?= $actions ?></div></div>
        <?php endif; ?>
    </div>
</div>

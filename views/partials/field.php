<?php
/**
 * A labelled form field. Options:
 * name, label, type (text|email|number|date|datetime-local|password|textarea|select|checkbox|file|color),
 * value, options (for select: [value => label]), placeholder, hint, required, attrs, col
 */
$type = $type ?? 'text';
$value = old($name, $value ?? '');
$error = field_error($name);
$id = 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
$required = ! empty($required);
$attrs = $attrs ?? '';
?>
<div class="<?= e($col ?? 'mb-3') ?>">
    <?php if ($type === 'checkbox'): ?>
        <label class="form-check form-switch">
            <input type="hidden" name="<?= e($name) ?>" value="0">
            <input class="form-check-input" type="checkbox" name="<?= e($name) ?>" value="1" id="<?= e($id) ?>" <?= $value ? 'checked' : '' ?> <?= $attrs ?>>
            <span class="form-check-label"><?= e($label) ?></span>
            <?php if (! empty($hint)): ?><span class="form-check-description"><?= e($hint) ?></span><?php endif; ?>
        </label>
    <?php else: ?>
        <label class="form-label<?= $required ? ' required' : '' ?>" for="<?= e($id) ?>"><?= e($label) ?></label>
        <?php if ($type === 'textarea'): ?>
            <textarea class="form-control<?= $error ? ' is-invalid' : '' ?>" name="<?= e($name) ?>" id="<?= e($id) ?>" rows="<?= (int) ($rows ?? 3) ?>" placeholder="<?= e($placeholder ?? '') ?>" <?= $required ? 'required' : '' ?> <?= $attrs ?>><?= e($value) ?></textarea>
        <?php elseif ($type === 'select'): ?>
            <select class="form-select<?= $error ? ' is-invalid' : '' ?>" name="<?= e($name) ?>" id="<?= e($id) ?>" <?= $required ? 'required' : '' ?> <?= $attrs ?>>
                <?php if (isset($blank)): ?><option value=""><?= e($blank) ?></option><?php endif; ?>
                <?php foreach ($options as $optValue => $optLabel): ?>
                    <option value="<?= e($optValue) ?>" <?= (string) $optValue === (string) $value ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input class="form-control<?= $error ? ' is-invalid' : '' ?>" type="<?= e($type) ?>" name="<?= e($name) ?>" id="<?= e($id) ?>"
                   value="<?= $type === 'password' || $type === 'file' ? '' : e($value) ?>" placeholder="<?= e($placeholder ?? '') ?>" <?= $required ? 'required' : '' ?> <?= $attrs ?>>
        <?php endif; ?>
        <?php if ($error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php elseif (! empty($hint)): ?><small class="form-hint"><?= e($hint) ?></small><?php endif; ?>
    <?php endif; ?>
</div>

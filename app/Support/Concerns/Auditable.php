<?php

namespace App\Support\Concerns;

use App\Domain\Administration\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Records create/update/delete events for the model into the audit log.
 * Attributes listed in $auditExclude (and hidden attributes) are never written.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', [], $model->auditableAttributes($model->getAttributes())));
        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->auditableAttributes($model->getOriginal()), $changes);
            $model->writeAudit('updated', $old, $changes);
        });
        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->auditableAttributes($model->getOriginal()), []));
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    /** @param array<string, mixed> $attributes */
    protected function auditableAttributes(array $attributes): array
    {
        $exclude = array_merge($this->getHidden(), property_exists($this, 'auditExclude') ? $this->auditExclude : []);

        return array_diff_key($attributes, array_flip($exclude));
    }

    protected function writeAudit(string $event, array $old, array $new): void
    {
        AuditLog::record($event, $this, $old, $new);
    }
}

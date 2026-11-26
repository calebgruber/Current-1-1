<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Administration\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Maps audit log entries of a model to plain arrays for history timelines. */
class HistoryPresenter
{
    private const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @return list<array<string, mixed>> */
    public function for(Model $model, int $limit = 30): array
    {
        if (! method_exists($model, 'auditLogs')) {
            return [];
        }

        return $model->auditLogs()->with('user:id,name')->latest('id')->limit($limit)->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
                'changes' => $this->changes((array) ($log->old_values ?? []), (array) ($log->new_values ?? [])),
            ])->all();
    }

    /** @return list<array{field: string, from: mixed, to: mixed}> */
    private function changes(array $old, array $new): array
    {
        $out = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            if (in_array($field, self::HIDDEN, true) || in_array($field, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $out[] = ['field' => (string) $field, 'from' => $this->scalar($old[$field] ?? null), 'to' => $this->scalar($new[$field] ?? null)];
            if (count($out) >= 12) {
                break;
            }
        }

        return $out;
    }

    private function scalar(mixed $value): mixed
    {
        if (is_array($value)) {
            return mb_substr((string) json_encode($value), 0, 200);
        }

        return is_string($value) ? mb_substr($value, 0, 200) : $value;
    }
}

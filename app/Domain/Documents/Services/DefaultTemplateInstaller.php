<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;

/** Installs or resets the starter templates from DefaultTemplates. */
final class DefaultTemplateInstaller
{
    /** @return int templates created or reset */
    public static function install(?string $onlyType, bool $reset, ?int $userId = null): int
    {
        $count = 0;
        DB::transaction(function () use ($onlyType, $reset, $userId, &$count) {
            foreach (DefaultTemplates::all() as $type => $def) {
                if ($onlyType && $type !== $onlyType) {
                    continue;
                }
                $existing = DocumentTemplate::query()->where('type', $type)->where('name', $def['name'])->first();
                if ($existing && ! $reset) {
                    continue;
                }
                $attributes = [
                    'name' => $def['name'], 'type' => $type, 'entity_type' => $def['entity_type'], 'editor' => 'visual',
                    'blocks' => $def['blocks'], 'html' => null, 'css' => null, 'paper_size' => 'letter',
                    'orientation' => $def['orientation'] ?? 'portrait', 'margins' => null,
                    'watermark' => $def['watermark'] ?? null, 'updated_by' => $userId,
                ];
                if ($existing) {
                    $existing->fill($attributes);
                    $existing->version = (int) $existing->version + 1;
                    $existing->save();
                } else {
                    $hasDefault = DocumentTemplate::query()->where('type', $type)->where('is_default', true)->exists();
                    DocumentTemplate::create($attributes + ['version' => 1, 'is_default' => ! $hasDefault]);
                }
                $count++;
            }
        });

        return $count;
    }

}

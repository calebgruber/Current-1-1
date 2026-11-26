<?php

namespace Database\Seeders;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\DefaultTemplates;
use Illuminate\Database\Seeder;

/** Installs a default visual template for each paperwork type that has none. */
class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DefaultTemplates::all() as $type => $template) {
            if (DocumentTemplate::query()->where('type', $type)->exists()) {
                continue;
            }
            DocumentTemplate::query()->create([
                'name' => $template['name'],
                'type' => $type,
                'entity_type' => $template['entity_type'],
                'editor' => 'visual',
                'blocks' => $template['blocks'],
                'watermark' => $template['watermark'] ?? null,
                'orientation' => $template['orientation'] ?? 'portrait',
                'paper_size' => 'a4',
                'is_default' => true,
            ]);
        }
    }
}

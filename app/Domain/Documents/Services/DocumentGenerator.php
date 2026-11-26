<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a template against a real job / maintenance record, stores the
 * GeneratedDocument (HTML snapshot) and its PDF on the private disk.
 */
final class DocumentGenerator
{
    /** Morph alias → [model class, view permission]. */
    public const ENTITIES = [
        'job' => [ProductionJob::class, 'jobs.jobs.view'],
        'maintenance' => [MaintenanceRecord::class, 'maintenance.records.view'],
    ];

    public const DISK = 'local';

    public function __construct(
        private readonly DocumentRenderer $renderer,
        private readonly DocumentContextBuilder $contexts,
    ) {}

    public static function viewPermission(string $entityType): ?string
    {
        return self::ENTITIES[$entityType][1] ?? null;
    }

    public static function findEntity(string $entityType, int $id): ?Model
    {
        $class = self::ENTITIES[$entityType][0] ?? null;

        return $class ? $class::query()->find($id) : null;
    }

    public function context(DocumentTemplate|string $template, Model $entity, ?User $user = null): array
    {
        $type = $template instanceof DocumentTemplate ? $template->type : $template;

        return $entity instanceof MaintenanceRecord
            ? $this->contexts->forMaintenance($entity, $type, $user)
            : $this->contexts->forJob($entity, $type, $user);
    }

    public static function entityLabel(Model $entity): string
    {
        return $entity instanceof MaintenanceRecord
            ? $entity->reference.' — '.$entity->title
            : $entity->number.' — '.$entity->name;
    }

    /** @throws \App\Domain\Documents\Exceptions\TemplateException */
    public function generate(DocumentTemplate $template, Model $entity, User $user): GeneratedDocument
    {
        return DB::transaction(function () use ($template, $entity, $user) {
            $label = DocumentTemplate::TYPES[$template->type] ?? $template->name;
            $doc = GeneratedDocument::create([
                'template_id' => $template->id,
                'type' => $template->type,
                'documentable_type' => $entity->getMorphClass(),
                'documentable_id' => $entity->getKey(),
                'title' => $label.' · '.self::entityLabel($entity),
                'html' => '',
                'created_by' => $user->id,
            ]);

            $context = $this->context($template, $entity, $user);
            $context['document']['number'] = $doc->number;
            $html = $this->renderer->documentFor($template, $context, $doc->title);
            $pdf = $this->renderer->pdf($html, $template->paper_size ?: 'letter', $template->orientation ?: 'portrait');

            $path = 'documents/'.$doc->number.'.pdf';
            Storage::disk(self::DISK)->put($path, $pdf);
            $doc->forceFill(['html' => $html, 'pdf_path' => $path])->save();

            return $doc;
        });
    }

    /** Return stored PDF bytes, regenerating from the HTML snapshot if the file is missing. */
    public function pdfBytes(GeneratedDocument $doc): string
    {
        $disk = Storage::disk(self::DISK);
        $path = (string) $doc->pdf_path;
        if ($path !== '' && preg_match('#^documents/[A-Za-z0-9_-]+\.pdf$#', $path) && $disk->exists($path)) {
            return (string) $disk->get($path);
        }
        $template = $doc->template;

        return $this->renderer->pdf((string) $doc->html, $template?->paper_size ?: 'letter', $template?->orientation ?: 'portrait');
    }
}

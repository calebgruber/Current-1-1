<?php

namespace App\Domain\Jobs\Services;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Inventory\Enums\TrackingType;
use App\Domain\Jobs\Enums\JobStage;
use App\Domain\Jobs\Models\Attachment;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Jobs\Models\Note;
use App\Domain\Jobs\Models\ProductionJob;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/** Shapes jobs into the plain arrays consumed by the Vue pages. */
class JobPresenter
{
    public function row(ProductionJob $job): array
    {
        return [
            'id' => $job->id,
            'number' => $job->number,
            'name' => $job->name,
            'stage' => $job->stage->value,
            'stage_label' => $job->stage->label(),
            'color' => $job->color,
            'client' => $job->client?->name,
            'venue' => $job->venue?->name,
            'owner' => $job->owner?->name,
            'prep_starts_at' => $job->prep_starts_at?->toIso8601String(),
            'starts_at' => $job->starts_at?->toIso8601String(),
            'ends_at' => $job->ends_at?->toIso8601String(),
            'returns_at' => $job->returns_at?->toIso8601String(),
            'items_count' => (int) ($job->items_count ?? 0),
            'pull_total' => (int) ($job->pull_total ?? 0),
        ];
    }

    public function header(ProductionJob $job, ?User $user): array
    {
        return $this->row($job) + [
            'description' => $job->description,
            'workflow_status' => $job->workflow_status,
            'client_contact_id' => $job->client_contact_id,
            'venue_location_id' => $job->venue_location_id,
            'owner_id' => $job->owner_id,
            'hold_starts_at' => $job->holdStartsAt()?->toIso8601String(),
            'hold_ends_at' => $job->holdEndsAt()?->toIso8601String(),
            'allows_allocation_edits' => $job->stage->allowsAllocationEdits(),
            'is_warehouse_active' => $job->stage->isWarehouseActive(),
            'hold_status' => $job->stage->reservationStatus()?->value,
            'allowed_transitions' => $this->transitions($job, $user),
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];
    }

    /** Next stages with a flag telling the UI whether this user may take them. */
    public function transitions(ProductionJob $job, ?User $user): array
    {
        $canMove = $user && ($user->can('jobs.stages.edit') || $user->can('jobs.jobs.edit'));
        $canApprove = $user && ($user->can('jobs.jobs.approve') || $user->can('jobs.stages.approve'));

        return array_map(fn (JobStage $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'requires_approval' => $s->requiresApproval(),
            'forward' => $s->position() > $job->stage->position() && $s !== JobStage::Archived,
            'allowed' => $canMove && (! $s->requiresApproval() || $canApprove),
        ], $job->stage->allowedTransitions());
    }

    public function item(JobItem $item): array
    {
        $product = $item->product;

        return [
            'id' => $item->id,
            'parent_item_id' => $item->parent_item_id,
            'section' => $item->section,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'serialized' => $product->tracking === TrackingType::Serialized,
            ] : null,
            'required_quantity' => $item->required_quantity,
            'spare_quantity' => $item->spare_quantity,
            'pull_quantity' => $item->pull_quantity,
            'picked_quantity' => $item->picked_quantity,
            'checked_out_quantity' => $item->checked_out_quantity,
            'returned_quantity' => $item->returned_quantity,
            'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
            'notes' => $item->notes,
            'sort_order' => $item->sort_order,
            'assets' => $item->relationLoaded('assignedAssets') ? $item->assignedAssets->map(fn ($a) => [
                'id' => $a->asset_id,
                'asset_number' => $a->asset?->asset_number,
                'serial_number' => $a->asset?->serial_number,
                'role' => $a->role,
                'status' => $a->status,
            ])->values()->all() : [],
        ];
    }

    public function note(Note $note, ?User $user): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'is_pinned' => (bool) $note->is_pinned,
            'user' => $note->user?->name,
            'created_at' => $note->created_at?->toIso8601String(),
            'can_delete' => $user && ($note->user_id === $user->id || $user->can('jobs.jobs.admin')),
        ];
    }

    public function attachment(ProductionJob $job, Attachment $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->original_name,
            'mime_type' => $a->mime_type,
            'size' => (int) $a->size,
            'user' => $a->user?->name,
            'created_at' => $a->created_at?->toIso8601String(),
            'url' => route('jobs.attachments.download', [$job, $a]),
        ];
    }

    /** Document templates applicable to jobs (documents module may not be installed yet). */
    public function documentTemplates(): array
    {
        if (! class_exists(DocumentTemplate::class) || ! Schema::hasTable('document_templates')) {
            return [];
        }
        $query = DocumentTemplate::query();
        if (Schema::hasColumn('document_templates', 'entity_type')) {
            $query->where('entity_type', 'job');
        }
        if (Schema::hasColumn('document_templates', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->orderByDesc('is_default')->orderBy('name')->limit(100)->get(['id', 'name', 'type'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'type' => $t->type])->all();
    }

    public function documents(ProductionJob $job): array
    {
        if (! Schema::hasTable('generated_documents')) {
            return [];
        }

        return $job->documents()->with('creator:id,name')->limit(20)->get()->map(fn ($d) => [
            'id' => $d->id,
            'title' => $d->title,
            'type' => $d->type,
            'created_by' => $d->creator?->name,
            'created_at' => $d->created_at?->toIso8601String(),
            'url' => \Illuminate\Support\Facades\Route::has('documents.show') ? route('documents.show', $d->id) : null,
        ])->all();
    }
}

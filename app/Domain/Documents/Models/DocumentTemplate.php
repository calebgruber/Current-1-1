<?php

namespace App\Domain\Documents\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    use Auditable;

    /** Every configurable paperwork type. */
    public const TYPES = [
        'quotation' => 'Quotation',
        'project_quotation' => 'Project quotation',
        'draft_order' => 'Draft order',
        'rental_agreement' => 'Rental agreement',
        'picking_list' => 'Picking list',
        'pull_sheet' => 'Pull sheet',
        'pack_sheet' => 'Pack sheet',
        'collection_note' => 'Collection note',
        'delivery_note' => 'Delivery note',
        'invoice' => 'Invoice',
        'return_sheet' => 'Return sheet',
        'maintenance_report' => 'Maintenance report',
        'damage_report' => 'Damage report',
    ];

    protected $fillable = [
        'name', 'type', 'entity_type', 'editor', 'blocks', 'html', 'css', 'paper_size', 'orientation',
        'margins', 'watermark', 'is_default', 'version', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'margins' => 'array', 'is_default' => 'boolean', 'version' => 'integer'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'template_id');
    }
}

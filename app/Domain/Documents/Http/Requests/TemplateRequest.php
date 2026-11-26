<?php

namespace App\Domain\Documents\Http\Requests;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\DocumentRenderer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route('template') ? 'documents.templates.edit' : 'documents.templates.create';

        return (bool) $this->user()?->can($ability);
    }

    /** Shared draft rules (also used by the live preview). */
    public static function draftRules(): array
    {
        return [
            'editor' => ['required', Rule::in(['visual', 'code'])],
            'blocks' => ['nullable', 'array', 'max:200'],
            'html' => ['nullable', 'string', 'max:500000'],
            'css' => ['nullable', 'string', 'max:100000'],
            'paper_size' => ['required', Rule::in(DocumentRenderer::PAPER_SIZES)],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'margins' => ['nullable', 'array'],
            'margins.top' => ['nullable', 'numeric', 'between:0,50'],
            'margins.right' => ['nullable', 'numeric', 'between:0,50'],
            'margins.bottom' => ['nullable', 'numeric', 'between:0,50'],
            'margins.left' => ['nullable', 'numeric', 'between:0,50'],
            'watermark' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function rules(): array
    {
        return self::draftRules() + [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(DocumentTemplate::TYPES))],
            'entity_type' => ['required', Rule::in(['job', 'maintenance'])],
            'is_default' => ['boolean'],
        ];
    }
}

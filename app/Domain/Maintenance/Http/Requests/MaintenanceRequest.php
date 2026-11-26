<?php

namespace App\Domain\Maintenance\Http\Requests;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceRequest extends FormRequest
{
    public const TYPES = ['repair', 'inspection', 'pat_test', 'cleaning', 'damage', 'calibration'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function authorize(): bool
    {
        return $this->user()->can($this->route('record') instanceof MaintenanceRecord ? 'maintenance.records.edit' : 'maintenance.records.create');
    }

    public function rules(): array
    {
        $record = $this->route('record');

        return [
            'product_id' => ['required', 'integer', $record instanceof MaintenanceRecord ? Rule::in([$record->product_id]) : Rule::exists('products', 'id')->whereNull('deleted_at')],
            'asset_id' => ['nullable', 'integer', $record instanceof MaintenanceRecord ? Rule::in([$record->asset_id]) : Rule::exists('assets', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'type' => ['required', Rule::in(self::TYPES)],
            'status' => ['required', Rule::in(array_column(MaintenanceStatus::cases(), 'value'))],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'resolution' => ['nullable', 'string', 'max:10000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'job_id' => ['nullable', 'integer', Rule::exists('production_jobs', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->when($record?->job_id, fn ($q, $id) => $q->orWhere('id', $id)))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}

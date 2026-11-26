<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Settings\Services\SettingsRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GeneralSettingsController extends Controller
{
    public function index(SettingsRepository $settings)
    {
        return view('settings.general.index', ['settings' => $settings->all()]);
    }

    public function update(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'timezone'],
            'date_format' => ['required', Rule::in(['M j, Y', 'j M Y', 'Y-m-d', 'm/d/Y', 'd/m/Y'])],
            'default_job_duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_label' => ['required', 'string', 'max:40'],
            'label_inventory' => ['required', 'string', 'max:40'],
            'label_jobs' => ['required', 'string', 'max:40'],
            'label_warehouse' => ['required', 'string', 'max:40'],
            'label_reservations' => ['required', 'string', 'max:40'],
            'label_contacts' => ['required', 'string', 'max:40'],
            'label_documents' => ['required', 'string', 'max:40'],
            'label_reports' => ['required', 'string', 'max:40'],
            'sound_enabled' => ['required', 'boolean'],
            'allow_over_pull' => ['required', 'boolean'],
            'block_overbooking' => ['required', 'boolean'],
            'count_tentative' => ['required', 'boolean'],
        ]);

        $values = [
            'general.organization_name' => $data['organization_name'],
            'general.currency' => $data['currency'],
            'general.timezone' => $data['timezone'],
            'general.date_format' => $data['date_format'],
            'general.default_job_duration_days' => (int) $data['default_job_duration_days'],
            'general.tax_rate' => (float) $data['tax_rate'],
            'general.tax_label' => $data['tax_label'],
            'ui.label_inventory' => $data['label_inventory'],
            'ui.label_jobs' => $data['label_jobs'],
            'ui.label_warehouse' => $data['label_warehouse'],
            'ui.label_reservations' => $data['label_reservations'],
            'ui.label_contacts' => $data['label_contacts'],
            'ui.label_documents' => $data['label_documents'],
            'ui.label_reports' => $data['label_reports'],
            'warehouse.sound_enabled' => (bool) $data['sound_enabled'],
            'warehouse.allow_over_pull' => (bool) $data['allow_over_pull'],
            'reservations.block_overbooking' => (bool) $data['block_overbooking'],
            'reservations.count_tentative' => (bool) $data['count_tentative'],
        ];

        $settings->setMany($values);
        AuditLog::record('settings.general_updated', null, [], $values, 'Organization settings updated');

        return back()->with('success', 'Organization settings saved.');
    }
}

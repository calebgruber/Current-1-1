<?php

namespace Tests\Feature\Settings;

use App\Domain\Settings\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_settings_page_requires_view_permission(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get('/settings/general')
            ->assertForbidden();

        $this->actingAs($this->userWithPermissions(['settings.general.view']))
            ->get('/settings/general')
            ->assertOk()
            ->assertSee('Organization settings')
            ->assertSee('Module names')
            ->assertSee('Workflow defaults');
    }

    public function test_general_settings_can_be_updated_and_are_deployment_wide(): void
    {
        $this->actingAs($this->userWithPermissions(['settings.general.edit']))
            ->put('/settings/general', [
                'organization_name' => 'Campus Stage Shop',
                'currency' => 'CAD',
                'timezone' => 'America/Toronto',
                'date_format' => 'Y-m-d',
                'default_job_duration_days' => 5,
                'tax_rate' => 5,
                'tax_label' => 'GST',
                'label_inventory' => 'Shop stock',
                'label_jobs' => 'Productions',
                'label_maintenance' => 'Repairs',
                'label_warehouse' => 'Prep room',
                'label_reservations' => 'Holds',
                'label_contacts' => 'People',
                'label_documents' => 'Paperwork',
                'label_reports' => 'Analytics',
                'sound_enabled' => '1',
                'allow_over_pull' => '0',
                'block_overbooking' => '1',
                'count_tentative' => '0',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Organization settings saved.');

        $settings = app(SettingsRepository::class);
        $this->assertSame('Campus Stage Shop', $settings->get('general.organization_name'));
        $this->assertSame('CAD', $settings->get('general.currency'));
        $this->assertSame('Productions', $settings->get('ui.label_jobs'));
        $this->assertSame('Repairs', $settings->get('ui.label_maintenance'));
        $this->assertTrue($settings->get('warehouse.sound_enabled'));
        $this->assertFalse($settings->get('warehouse.allow_over_pull'));
    }

    public function test_invalid_deployment_labels_are_rejected(): void
    {
        $this->actingAs($this->userWithPermissions(['settings.general.edit']))
            ->put('/settings/general', ['label_jobs' => str_repeat('x', 41)])
            ->assertSessionHasErrors(['organization_name', 'label_jobs', 'timezone']);
    }
}

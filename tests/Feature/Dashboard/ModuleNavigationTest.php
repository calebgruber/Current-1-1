<?php

namespace Tests\Feature\Dashboard;

use App\Support\Navigation\ModuleNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ModuleNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_and_sub_tabs_follow_permissions(): void
    {
        $user = $this->userWithPermissions(['contacts.contacts.view']);

        $modules = collect(app(ModuleNavigation::class)->for($user, Request::create('/contacts')));
        $keys = $modules->pluck('key')->all();

        $this->assertContains('home', $keys);
        $this->assertContains('contacts', $keys);
        $this->assertContains('account', $keys);
        $this->assertNotContains('inventory', $keys);
        $this->assertNotContains('settings', $keys);

        $contacts = $modules->firstWhere('key', 'contacts');
        $this->assertSame(['Directory'], array_column($contacts['tabs'], 'label'));
    }

    public function test_layout_renders_colour_coded_modules_sub_tabs_and_loader(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSee('aria-label="Modules"', false)
            ->assertSee('class="st-module is-active"', false)
            ->assertSee('st-subnav', false)
            ->assertSeeText('Products')
            ->assertSeeText('Locations')
            ->assertSeeText('New product')
            ->assertSee('id="st-page-loader"', false);
    }
}

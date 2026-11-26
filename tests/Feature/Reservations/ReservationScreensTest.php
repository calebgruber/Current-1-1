<?php

namespace Tests\Feature\Reservations;

use App\Domain\Inventory\Models\Product;
use App\Domain\Reservations\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_pages_render_blade_views_and_keep_the_json_check_endpoint(): void
    {
        $product = Product::factory()->bulk()->create(['name' => 'Lustr']);
        $reservation = Reservation::query()->create([
            'product_id' => $product->id,
            'name' => 'Opening night',
            'quantity' => 3,
            'spare_quantity' => 1,
            'starts_at' => '2030-01-01 10:00:00',
            'ends_at' => '2030-01-02 10:00:00',
            'status' => 'tentative',
        ]);
        $user = $this->userWithPermissions([
            'reservations.reservations.view',
            'reservations.reservations.create',
            'reservations.reservations.edit',
        ]);

        $this->actingAs($user)->get('/reservations?status=tentative')
            ->assertOk()
            ->assertViewIs('reservations.index')
            ->assertViewHas('reservations', fn ($rows) => $rows->total() === 1 && $rows->items()[0]['label'] === 'Opening night')
            ->assertSee('Lustr');

        $this->get("/reservations/create?product_id={$product->id}")
            ->assertOk()
            ->assertViewIs('reservations.form')
            ->assertViewHas('defaults.product_id', $product->id);

        $this->get("/reservations/{$reservation->id}")
            ->assertOk()
            ->assertViewIs('reservations.show')
            ->assertViewHas('reservation.label', 'Opening night')
            ->assertSee('Opening night');

        $this->get("/reservations/{$reservation->id}/edit")
            ->assertOk()
            ->assertViewIs('reservations.form')
            ->assertViewHas('reservation.id', $reservation->id);

        $this->postJson('/reservations/check', [
            'product_id' => $product->id,
            'quantity' => 1,
            'starts_at' => '2030-01-01T10:00:00Z',
            'ends_at' => '2030-01-01T11:00:00Z',
        ])->assertOk()->assertJsonStructure(['conflict']);
    }

    public function test_timeline_view_renders_overlapping_reservations(): void
    {
        $product = Product::factory()->bulk()->create(['name' => 'Console']);
        Reservation::query()->create([
            'product_id' => $product->id,
            'name' => 'Matinee',
            'quantity' => 1,
            'starts_at' => '2030-02-01 10:00:00',
            'ends_at' => '2030-02-01 12:00:00',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->userWithPermissions(['reservations.reservations.view']))
            ->get('/reservations?view=timeline&from=2030-02-01&to=2030-02-02')
            ->assertOk()
            ->assertViewIs('reservations.index')
            ->assertViewHas('timeline.groups', fn ($groups) => count($groups) === 1 && $groups[0]['rows'][0]['label'] === 'Matinee')
            ->assertSee('Console')
            ->assertSee('Matinee');
    }
}

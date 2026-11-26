<?php

namespace Database\Seeders;

use App\Domain\Assets\Models\Asset;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Location;
use App\Domain\Inventory\Models\Manufacturer;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductAccessory;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\Tag;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Jobs\Services\JobService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistic sample data for a university theatre department.
 * php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $warehouse = Location::query()->firstOrCreate(['code' => 'WH'], ['name' => 'Main Warehouse', 'type' => 'warehouse']);
        $electrics = Location::query()->firstOrCreate(['code' => 'WH-ELX'], ['name' => 'Electrics Shop', 'type' => 'room', 'parent_id' => $warehouse->id]);
        $audio = Location::query()->firstOrCreate(['code' => 'WH-AUD'], ['name' => 'Audio Room', 'type' => 'room', 'parent_id' => $warehouse->id]);
        $venue = Location::query()->firstOrCreate(['code' => 'MAIN'], ['name' => 'Main Stage Theatre', 'type' => 'venue']);

        $lighting = $this->category('Lighting');
        $fixtures = $this->category('Fixtures', $lighting);
        $movers = $this->category('Moving lights', $lighting);
        $cable = $this->category('Cable');
        $rigging = $this->category('Rigging');
        $sound = $this->category('Audio');
        $consumables = $this->category('Consumables');

        $etc = Manufacturer::query()->firstOrCreate(['name' => 'ETC']);
        $shure = Manufacturer::query()->firstOrCreate(['name' => 'Shure']);
        $yamaha = Manufacturer::query()->firstOrCreate(['name' => 'Yamaha']);
        $mfr = Manufacturer::query()->firstOrCreate(['name' => 'Generic']);

        $lustr = $this->product('ETC Source Four LED Series 3 Lustr', 'LX-LUSTR3', $fixtures, $etc, 'serialized', 4200, ['hanging-fixture'], $electrics, 30);
        $mover = $this->product('ETC Halcyon Titanium', 'LX-HALCYON', $movers, $etc, 'serialized', 12500, ['moving-light', 'hanging-fixture'], $electrics, 8);
        $clamp = $this->product('C-clamp', 'RIG-CCLAMP', $rigging, $mfr, 'bulk', 18, ['clamp'], $electrics, 200);
        $safety = $this->product('Safety cable 3ft', 'RIG-SAFETY', $rigging, $mfr, 'bulk', 9, ['safety-cable'], $electrics, 200);
        $powercon = $this->product('PowerCON True1 cable 10ft', 'CBL-PCT1-10', $cable, $mfr, 'bulk', 45, ['powercon'], $electrics, 60);
        $this->product('DMX 5-pin cable 25ft', 'CBL-DMX5-25', $cable, $mfr, 'bulk', 38, ['dmx'], $electrics, 80);
        $ulxd = $this->product('Shure ULXD1 bodypack transmitter', 'AUD-ULXD1', $sound, $shure, 'serialized', 1100, ['wireless-mic'], $audio, 16);
        $this->product('Yamaha Rio1608-D2 stage box', 'AUD-RIO1608', $sound, $yamaha, 'serialized', 6800, ['dante'], $audio, 2);
        $this->product('Network switch 8-port managed', 'NET-SW8', $sound, $mfr, 'bulk', 350, ['network-switch'], $audio, 4);
        $battery = $this->product('AA battery', 'CON-AA', $consumables, $mfr, 'bulk', 1, ['battery-aa'], $audio, 400);

        foreach ([[$lustr, $clamp, 'required'], [$lustr, $safety, 'safety'], [$mover, $clamp, 'required'], [$mover, $safety, 'safety'], [$mover, $powercon, 'required'], [$ulxd, $battery, 'consumable']] as [$parent, $accessory, $type]) {
            ProductAccessory::query()->firstOrCreate(
                ['product_id' => $parent->id, 'accessory_product_id' => $accessory->id],
                ['quantity' => $type === 'consumable' ? 2 : 1, 'type' => $type, 'auto_add' => $type !== 'consumable'],
            );
        }

        $client = Contact::query()->firstOrCreate(['email' => 'producer@example.edu'], ['type' => 'person', 'name' => 'Jordan Producer', 'title' => 'Producing Director']);

        if (! ProductionJob::query()->where('name', 'Into the Woods — Main Stage')->exists()) {
            $start = now()->addDays(14)->startOfDay()->addHours(9);
            $job = ProductionJob::query()->create([
                'name' => 'Into the Woods — Main Stage',
                'stage' => 'draft',
                'client_contact_id' => $client->id,
                'venue_location_id' => $venue->id,
                'prep_starts_at' => $start->copy()->subDays(3),
                'starts_at' => $start,
                'ends_at' => $start->copy()->addDays(10),
                'returns_at' => $start->copy()->addDays(11),
                'description' => 'Fall musical. 20 Lustr fixtures required with 5 spares.',
            ]);
            $jobs = app(JobService::class);
            $jobs->addItem($job, $lustr, 20, 5);
            $jobs->addItem($job, $mover, 6, 1);
            $jobs->addItem($job, $ulxd, 12, 2);
        }
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'parent_id' => $parent?->id]);
    }

    /** @param list<string> $tags */
    private function product(string $name, string $sku, Category $category, Manufacturer $manufacturer, string $tracking, float $cost, array $tags, Location $location, int $quantity): Product
    {
        $product = Product::query()->firstOrCreate(['sku' => $sku], [
            'name' => $name,
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'tracking' => $tracking,
            'replacement_cost' => $cost,
            'barcode' => $sku,
            'default_location_id' => $location->id,
            'is_active' => true,
        ]);

        $product->tags()->syncWithoutDetaching(
            collect($tags)->map(fn ($t) => Tag::query()->firstOrCreate(['slug' => $t], ['name' => $t])->id)->all()
        );

        if ($tracking === 'serialized') {
            for ($i = $product->assets()->count() + 1; $i <= $quantity; $i++) {
                Asset::query()->create([
                    'product_id' => $product->id,
                    'asset_number' => sprintf('%s-%03d', $sku, $i),
                    'serial_number' => strtoupper(Str::random(10)),
                    'barcode' => sprintf('%s-%03d', $sku, $i),
                    'status' => 'available',
                    'condition' => 'good',
                    'location_id' => $location->id,
                ]);
            }
        } else {
            StockLevel::query()->firstOrCreate(['product_id' => $product->id, 'location_id' => $location->id], ['quantity' => $quantity]);
        }

        return $product;
    }
}

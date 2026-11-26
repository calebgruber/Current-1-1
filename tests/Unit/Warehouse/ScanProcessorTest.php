<?php

namespace Tests\Unit\Warehouse;

use App\Domain\Warehouse\DTOs\ScanResult;
use App\Domain\Warehouse\Enums\ScanMode;
use App\Domain\Warehouse\Services\ScanProcessor;
use PHPUnit\Framework\TestCase;

class ScanProcessorTest extends TestCase
{
    private ScanProcessor $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->p = new ScanProcessor;
    }

    private function line(array $o = []): array
    {
        return $o + ['id' => 100, 'product_id' => 1, 'product' => 'Lustr', 'required' => 2, 'spare' => 1, 'picked' => 0, 'checked_out' => 0, 'returned' => 0, 'assets' => []];
    }

    private function asset(array $o = []): array
    {
        return $o + ['kind' => 'asset', 'asset_id' => 50, 'asset_number' => 'LUS-050', 'product_id' => 1, 'product' => 'Lustr', 'status' => 'available'];
    }

    private function bulk(array $o = []): array
    {
        return $o + ['kind' => 'product', 'product_id' => 2, 'product' => 'Cable 5m'];
    }

    private function effect(ScanResult $r, string $type): ?array
    {
        foreach ($r->effects as $e) {
            if ($e['type'] === $type) {
                return $e;
            }
        }

        return null;
    }

    public function test_empty_scan_is_error(): void
    {
        $r = $this->p->process(ScanMode::Pick, '   ', $this->asset(), [$this->line()]);
        $this->assertSame(ScanResult::ERROR, $r->level);
        $this->assertSame('Empty scan', $r->title);
        $this->assertSame('error', $r->sound());
    }

    public function test_unknown_barcode_is_error_in_every_mode(): void
    {
        foreach (ScanMode::cases() as $mode) {
            $r = $this->p->process($mode, ' XYZ ', null, [$this->line()]);
            $this->assertSame(ScanResult::ERROR, $r->level, $mode->value);
            $this->assertSame('Unknown barcode', $r->title);
            $this->assertStringContainsString('"XYZ"', $r->message);
            $this->assertSame([], $r->effects);
        }
    }

    public function test_lookup_succeeds_without_job(): void
    {
        $r = $this->p->process(ScanMode::Lookup, 'LUS-050', $this->asset(['status' => 'checked_out']));
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('Lustr', $r->title);
        $this->assertSame('LUS-050 · Checked out', $r->message);
        $this->assertSame([], $r->effects);
        $this->assertSame(['kind' => 'asset', 'product_id' => 1, 'product' => 'Lustr', 'asset_id' => 50, 'asset_number' => 'LUS-050', 'status' => 'checked_out'], $r->item);

        $b = $this->p->process(ScanMode::Lookup, 'C5', $this->bulk());
        $this->assertSame('Bulk stock item', $b->message);
        $this->assertSame(['kind' => 'product', 'product_id' => 2, 'product' => 'Cable 5m'], $b->item);
    }

    public function test_damage_asset(): void
    {
        $r = $this->p->process(ScanMode::Damage, 'LUS-050', $this->asset());
        $this->assertSame(ScanResult::WARNING, $r->level);
        $this->assertSame('Damage logged', $r->title);
        $this->assertSame([
            ['type' => 'asset_status', 'asset_id' => 50, 'status' => 'damaged'],
            ['type' => 'create_maintenance', 'asset_id' => 50, 'product_id' => 1, 'kind' => 'damage'],
        ], $r->effects);
    }

    public function test_damage_already_damaged_asset(): void
    {
        $r = $this->p->process(ScanMode::Damage, 'LUS-050', $this->asset(['status' => 'damaged']));
        $this->assertSame(ScanResult::WARNING, $r->level);
        $this->assertSame('Already damaged', $r->title);
        $this->assertSame([], $r->effects);
    }

    public function test_damage_bulk_uses_quantity(): void
    {
        $r = $this->p->process(ScanMode::Damage, 'C5', $this->bulk(), [], 3);
        $this->assertSame('Damage logged', $r->title);
        $this->assertSame('3 × Cable 5m marked damaged.', $r->message);
        $this->assertSame([['type' => 'bulk_damage', 'product_id' => 2, 'quantity' => 3]], $r->effects);

        $r = $this->p->process(ScanMode::Damage, 'C5', $this->bulk(), [], 0);
        $this->assertSame(1, $r->effects[0]['quantity'], 'quantity is at least 1');
    }

    public function test_product_not_on_job(): void
    {
        foreach ([ScanMode::Pick, ScanMode::Prep, ScanMode::Stage, ScanMode::CheckOut, ScanMode::Return] as $mode) {
            $r = $this->p->process($mode, 'X', $this->asset(['product_id' => 9, 'product' => 'Spot']), [$this->line()]);
            $this->assertSame(ScanResult::ERROR, $r->level);
            $this->assertSame('Not on this job', $r->title);
            $this->assertSame('Spot is not on the pull sheet.', $r->message);
        }
    }

    public function test_unserviceable_asset_rejected_outside_return(): void
    {
        foreach (['maintenance', 'damaged', 'lost', 'retired'] as $status) {
            foreach ([ScanMode::Pick, ScanMode::CheckOut, ScanMode::Prep, ScanMode::Stage] as $mode) {
                $r = $this->p->process($mode, 'LUS-050', $this->asset(['status' => $status]), [$this->line()]);
                $this->assertSame(ScanResult::ERROR, $r->level);
                $this->assertSame('Do not use', $r->title);
                $this->assertStringContainsString("is {$status}", $r->message);
            }
        }
    }

    public function test_pick_first_active_unit(): void
    {
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), [$this->line()]);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('Picked: Lustr', $r->title);
        $this->assertSame('Active 1 of 2', $r->message);
        $this->assertSame('active', $r->role);
        $this->assertSame([
            ['type' => 'increment', 'job_item_id' => 100, 'field' => 'picked_quantity', 'quantity' => 1],
            ['type' => 'assign_asset', 'job_item_id' => 100, 'asset_id' => 50, 'role' => 'active', 'status' => 'picked'],
            ['type' => 'asset_status', 'asset_id' => 50, 'status' => 'allocated'],
        ], $r->effects);
        $this->assertSame(1, $r->line['picked']);
        $this->assertSame(1, $r->line['done']);
        $this->assertSame(3, $r->line['pull']);
    }

    public function test_pick_last_active_announces_spares(): void
    {
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), [$this->line(['picked' => 1])]);
        $this->assertSame('active', $r->role);
        $this->assertSame('Active 2 of 2 — all active units done, now pulling 1 spare(s).', $r->message);
    }

    public function test_pick_spare_and_completion(): void
    {
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), [$this->line(['picked' => 2])]);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('spare', $r->role);
        $this->assertSame('Spare 1 of 1 · Lustr complete (3/3).', $r->message);
        $this->assertSame('spare', $this->effect($r, 'assign_asset')['role']);
    }

    public function test_pick_without_spares_completes_cleanly(): void
    {
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), [$this->line(['spare' => 0, 'picked' => 1])]);
        $this->assertSame('Active 2 of 2 · Lustr complete (2/2).', $r->message);
    }

    public function test_over_scan_beyond_pull_including_spares_warns(): void
    {
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), [$this->line(['picked' => 3])]);
        $this->assertSame(ScanResult::WARNING, $r->level);
        $this->assertSame('Over pull', $r->title);
        $this->assertSame('All 3 × Lustr are already picked. Put this one back.', $r->message);
        $this->assertSame([], $r->effects);
    }

    public function test_bulk_pick_quantity_and_over_pull(): void
    {
        $line = $this->line(['product_id' => 2, 'product' => 'Cable 5m', 'required' => 20, 'spare' => 5, 'picked' => 10]);
        $r = $this->p->process(ScanMode::Pick, 'C5', $this->bulk(), [$line], 10);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('Active 20 of 20 — all active units done, now pulling 5 spare(s).', $r->message);
        $this->assertSame([['type' => 'increment', 'job_item_id' => 100, 'field' => 'picked_quantity', 'quantity' => 10]], $r->effects);

        $over = $this->p->process(ScanMode::Pick, 'C5', $this->bulk(), [$line], 16);
        $this->assertSame('Over pull', $over->title);

        $exact = $this->p->process(ScanMode::Pick, 'C5', $this->bulk(), [$line], 15);
        $this->assertSame('Spare 5 of 5 · Cable 5m complete (25/25).', $exact->message);
    }

    public function test_asset_scan_forces_quantity_one(): void
    {
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), [$this->line()], 99);
        $this->assertSame(1, $r->effects[0]['quantity']);
    }

    public function test_pick_already_picked_asset_warns(): void
    {
        $line = $this->line(['picked' => 1, 'assets' => [50 => 'picked']]);
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(['status' => 'allocated']), [$line]);
        $this->assertSame(ScanResult::WARNING, $r->level);
        $this->assertSame('Already scanned', $r->title);
        $this->assertSame('LUS-050 was already picked.', $r->message);

        $line = $this->line(['assets' => [50 => 'staged']]);
        $this->assertSame('Already scanned', $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), [$line])->title);
    }

    public function test_pick_allocated_asset_does_not_change_status(): void
    {
        $line = $this->line(['assets' => [50 => 'allocated']]);
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(['status' => 'allocated']), [$line]);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertNull($this->effect($r, 'asset_status'));
    }

    public function test_asset_out_on_another_job(): void
    {
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(['status' => 'checked_out']), [$this->line()]);
        $this->assertSame(ScanResult::ERROR, $r->level);
        $this->assertSame('Out on another job', $r->title);

        $r = $this->p->process(ScanMode::CheckOut, 'LUS-050', $this->asset(['status' => 'checked_out']), [$this->line()]);
        $this->assertSame('Out on another job', $r->title);
    }

    public function test_prefers_line_already_holding_asset_then_first_with_room(): void
    {
        $lines = [
            $this->line(['id' => 1, 'required' => 1, 'spare' => 0, 'picked' => 1]),
            $this->line(['id' => 2, 'required' => 2, 'spare' => 0, 'picked' => 0]),
            $this->line(['id' => 3, 'required' => 2, 'spare' => 0, 'picked' => 0, 'assets' => [50 => 'allocated']]),
        ];
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), $lines);
        $this->assertSame(3, $r->effects[0]['job_item_id']);
        $this->assertSame('Active 2 of 5', $r->message);

        $r = $this->p->process(ScanMode::Pick, 'LUS-051', $this->asset(['asset_id' => 51]), $lines);
        $this->assertSame(2, $r->effects[0]['job_item_id']);
    }

    public function test_checkout_picked_asset(): void
    {
        $line = $this->line(['picked' => 1, 'assets' => [50 => 'staged']]);
        $r = $this->p->process(ScanMode::CheckOut, 'LUS-050', $this->asset(['status' => 'allocated']), [$line]);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('Checked out: Lustr', $r->title);
        $this->assertSame('Active 1 of 2', $r->message);
        $this->assertSame([
            ['type' => 'increment', 'job_item_id' => 100, 'field' => 'checked_out_quantity', 'quantity' => 1],
            ['type' => 'assign_asset', 'job_item_id' => 100, 'asset_id' => 50, 'role' => 'active', 'status' => 'checked_out'],
            ['type' => 'asset_status', 'asset_id' => 50, 'status' => 'checked_out'],
        ], $r->effects);
        $this->assertSame(1, $r->line['checked_out']);
    }

    public function test_checkout_unpicked_asset_warns_but_applies(): void
    {
        $r = $this->p->process(ScanMode::CheckOut, 'LUS-050', $this->asset(), [$this->line()]);
        $this->assertSame(ScanResult::WARNING, $r->level);
        $this->assertSame('Checked out (not picked)', $r->title);
        $this->assertSame('Active 1 of 2 — this unit skipped the pick step.', $r->message);
        $this->assertCount(3, $r->effects);
        $this->assertSame('checked_out', $this->effect($r, 'asset_status')['status']);
    }

    public function test_checkout_already_checked_out_on_this_job(): void
    {
        $line = $this->line(['checked_out' => 1, 'assets' => [50 => 'checked_out']]);
        $r = $this->p->process(ScanMode::CheckOut, 'LUS-050', $this->asset(['status' => 'checked_out']), [$line]);
        $this->assertSame('Already scanned', $r->title);
        $this->assertSame('LUS-050 was already checked out.', $r->message);
    }

    public function test_checkout_over_pull(): void
    {
        $line = $this->line(['checked_out' => 3]);
        $r = $this->p->process(ScanMode::CheckOut, 'LUS-050', $this->asset(), [$line]);
        $this->assertSame('Over pull', $r->title);
        $this->assertStringContainsString('already checked out', $r->message);
    }

    public function test_prep_and_stage(): void
    {
        $line = $this->line(['assets' => [50 => 'picked']]);
        $r = $this->p->process(ScanMode::Prep, 'LUS-050', $this->asset(['status' => 'allocated']), [$line]);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('Prep: LUS-050', $r->title);
        $this->assertSame('Lustr marked prepped.', $r->message);
        $this->assertSame([['type' => 'assign_asset', 'job_item_id' => 100, 'asset_id' => 50, 'role' => null, 'status' => 'prepped']], $r->effects);

        $staged = $this->p->process(ScanMode::Stage, 'LUS-050', $this->asset(['status' => 'allocated']), [$line]);
        $this->assertSame('Stage: LUS-050', $staged->title);
        $this->assertSame('staged', $staged->effects[0]['status']);

        $again = $this->p->process(ScanMode::Prep, 'LUS-050', $this->asset(), [$this->line(['assets' => [50 => 'staged']])]);
        $this->assertSame(ScanResult::WARNING, $again->level);
        $this->assertSame('Already scanned', $again->title);
    }

    public function test_prep_unpicked_asset_and_bulk(): void
    {
        $r = $this->p->process(ScanMode::Prep, 'LUS-050', $this->asset(), [$this->line()]);
        $this->assertSame(ScanResult::WARNING, $r->level);
        $this->assertSame('Not picked', $r->title);

        $b = $this->p->process(ScanMode::Stage, 'C5', $this->bulk(), [$this->line(['product_id' => 2, 'product' => 'Cable 5m'])]);
        $this->assertSame(ScanResult::SUCCESS, $b->level);
        $this->assertSame('Stage: Cable 5m', $b->title);
        $this->assertSame('Bulk item confirmed.', $b->message);
        $this->assertSame([], $b->effects);
    }

    public function test_return_asset(): void
    {
        $line = $this->line(['checked_out' => 2, 'returned' => 1, 'assets' => [50 => 'checked_out', 51 => 'returned']]);
        $r = $this->p->process(ScanMode::Return, 'LUS-050', $this->asset(['status' => 'checked_out']), [$line]);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('Returned: Lustr', $r->title);
        $this->assertSame('2 of 2 back · all Lustr returned.', $r->message);
        $this->assertSame([
            ['type' => 'increment', 'job_item_id' => 100, 'field' => 'returned_quantity', 'quantity' => 1],
            ['type' => 'assign_asset', 'job_item_id' => 100, 'asset_id' => 50, 'role' => null, 'status' => 'returned'],
            ['type' => 'asset_status', 'asset_id' => 50, 'status' => 'available'],
        ], $r->effects);
        $this->assertSame(2, $r->line['returned']);
    }

    public function test_return_unserviceable_asset_keeps_status(): void
    {
        $line = $this->line(['checked_out' => 2, 'assets' => [50 => 'checked_out']]);
        $r = $this->p->process(ScanMode::Return, 'LUS-050', $this->asset(['status' => 'damaged']), [$line]);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('1 of 2 back', $r->message);
        $this->assertNull($this->effect($r, 'asset_status'));
    }

    public function test_return_already_returned_or_not_out(): void
    {
        $r = $this->p->process(ScanMode::Return, 'LUS-050', $this->asset(), [$this->line(['assets' => [50 => 'returned']])]);
        $this->assertSame(ScanResult::WARNING, $r->level);
        $this->assertSame('Already returned', $r->title);

        $r = $this->p->process(ScanMode::Return, 'LUS-050', $this->asset(), [$this->line(['assets' => [50 => 'picked']])]);
        $this->assertSame('Not out on this job', $r->title);
        $this->assertSame([], $r->effects);
    }

    public function test_return_bulk(): void
    {
        $lines = [
            $this->line(['id' => 1, 'product_id' => 2, 'product' => 'Cable 5m', 'checked_out' => 3, 'returned' => 3]),
            $this->line(['id' => 2, 'product_id' => 2, 'product' => 'Cable 5m', 'checked_out' => 5, 'returned' => 0]),
        ];
        $r = $this->p->process(ScanMode::Return, 'C5', $this->bulk(), $lines, 4);
        $this->assertSame(ScanResult::SUCCESS, $r->level);
        $this->assertSame('7 of 8 back', $r->message);
        $this->assertSame([['type' => 'increment', 'job_item_id' => 2, 'field' => 'returned_quantity', 'quantity' => 4]], $r->effects);

        $over = $this->p->process(ScanMode::Return, 'C5', $this->bulk(), $lines, 6);
        $this->assertSame(ScanResult::WARNING, $over->level);
        $this->assertSame('More than went out', $over->title);
        $this->assertSame('Only 8 × Cable 5m went out and 3 are back.', $over->message);
    }

    public function test_to_array(): void
    {
        $r = $this->p->process(ScanMode::Pick, 'LUS-050', $this->asset(), [$this->line()]);
        $arr = $r->toArray();
        $this->assertSame('success', $arr['level']);
        $this->assertSame('success', $arr['sound']);
        $this->assertSame('active', $arr['role']);
        $this->assertArrayNotHasKey('effects', $arr);
    }
}

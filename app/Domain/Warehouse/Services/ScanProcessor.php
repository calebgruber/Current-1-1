<?php

namespace App\Domain\Warehouse\Services;

use App\Domain\Warehouse\DTOs\ScanResult;
use App\Domain\Warehouse\Enums\ScanMode;

/**
 * Pure decision logic for a single barcode scan.
 *
 * $match (what the code resolved to) is null for unknown codes, otherwise:
 *   ['kind' => 'asset', 'asset_id', 'asset_number', 'product_id', 'product', 'status']
 *   ['kind' => 'product', 'product_id', 'product']   (bulk stock barcode)
 *
 * $lines are the job's allocations:
 *   ['id', 'product_id', 'product', 'required', 'spare', 'picked', 'checked_out', 'returned',
 *    'assets' => [asset_id => assignment status]]
 *
 * Returns a ScanResult whose level drives the green/amber/red display and
 * sounds, and whose effects are applied transactionally by WarehouseService.
 */
final class ScanProcessor
{
    private const UNSERVICEABLE = ['maintenance', 'damaged', 'lost', 'retired'];

    private const ORDER = ['allocated' => 0, 'picked' => 1, 'prepped' => 2, 'staged' => 3, 'checked_out' => 4, 'returned' => 5];

    public function process(ScanMode $mode, string $code, ?array $match, array $lines = [], int $quantity = 1): ScanResult
    {
        $code = trim($code);
        if ($code === '') {
            return ScanResult::error('Empty scan', 'No barcode was received. Try scanning again.');
        }
        if ($match === null) {
            return ScanResult::error('Unknown barcode', "\"{$code}\" does not match any asset, product or job.");
        }

        $quantity = max(1, $quantity);
        $isAsset = $match['kind'] === 'asset';
        if ($isAsset) {
            $quantity = 1;
        }
        $item = $this->describe($match);

        if ($mode === ScanMode::Lookup) {
            return new ScanResult(ScanResult::SUCCESS, $match['product'], $isAsset ? $match['asset_number'].' · '.ucfirst(str_replace('_', ' ', $match['status'])) : 'Bulk stock item', [], null, $item);
        }

        if ($mode === ScanMode::Damage) {
            if (! $isAsset) {
                return new ScanResult(ScanResult::WARNING, 'Damage logged', "{$quantity} × {$match['product']} marked damaged.", [
                    ['type' => 'bulk_damage', 'product_id' => $match['product_id'], 'quantity' => $quantity],
                ], null, $item);
            }
            if ($match['status'] === 'damaged') {
                return ScanResult::warning('Already damaged', "{$match['asset_number']} is already marked damaged.", [], null, $item);
            }
            if ($match['status'] === 'retired') {
                return ScanResult::error('Retired asset', "{$match['asset_number']} is retired and cannot be reported as damaged.", $item);
            }

            return new ScanResult(ScanResult::WARNING, 'Damage logged', "{$match['asset_number']} marked damaged and sent to maintenance.", [
                ['type' => 'asset_status', 'asset_id' => $match['asset_id'], 'status' => 'damaged'],
                ['type' => 'create_maintenance', 'asset_id' => $match['asset_id'], 'product_id' => $match['product_id'], 'kind' => 'damage'],
            ], null, $item);
        }

        $productLines = array_values(array_filter($lines, fn ($l) => (int) $l['product_id'] === (int) $match['product_id']));
        if ($productLines === []) {
            return ScanResult::error('Not on this job', "{$match['product']} is not on the pull sheet.", $item);
        }

        if ($isAsset && in_array($match['status'], self::UNSERVICEABLE, true) && $mode !== ScanMode::Return) {
            return ScanResult::error('Do not use', "{$match['asset_number']} is ".str_replace('_', ' ', $match['status']).'. Put it back and pick another unit.', $item);
        }

        return match ($mode) {
            ScanMode::Pick, ScanMode::CheckOut => $this->outbound($mode, $match, $productLines, $quantity, $item),
            ScanMode::Prep, ScanMode::Stage => $this->progress($mode, $match, $productLines, $item),
            ScanMode::Return => $this->inbound($match, $productLines, $quantity, $item),
        };
    }

    private function outbound(ScanMode $mode, array $match, array $lines, int $quantity, array $item): ScanResult
    {
        $key = $mode === ScanMode::Pick ? 'picked' : 'checked_out';
        $isAsset = $match['kind'] === 'asset';
        $targetStatus = $mode->assetAssignmentStatus();

        if ($isAsset) {
            foreach ($lines as $line) {
                $current = $line['assets'][$match['asset_id']] ?? null;
                if ($current !== null && (self::ORDER[$current] ?? 0) >= self::ORDER[$targetStatus]) {
                    return ScanResult::warning('Already scanned', "{$match['asset_number']} was already ".str_replace('_', ' ', $current).'.', [], $this->summary($line), $item);
                }
            }
            if ($match['status'] === 'checked_out') {
                $onThisJob = array_filter($lines, fn ($l) => isset($l['assets'][$match['asset_id']]));
                if (! $onThisJob) {
                    return ScanResult::error('Out on another job', "{$match['asset_number']} is checked out elsewhere.", $item);
                }
            }
        }

        $required = array_sum(array_column($lines, 'required'));
        $pull = $required + array_sum(array_column($lines, 'spare'));
        $done = array_sum(array_column($lines, $key));

        if ($done + $quantity > $pull) {
            return ScanResult::warning(
                'Over pull',
                "All {$pull} × {$match['product']} are already ".($mode === ScanMode::Pick ? 'picked' : 'checked out').'. Put this one back.',
                [],
                $this->summary($lines[0]),
                $item,
            );
        }

        // Prefer a line already holding this asset, then the first with room.
        $line = null;
        if ($isAsset) {
            foreach ($lines as $l) {
                if (isset($l['assets'][$match['asset_id']])) {
                    $line = $l;
                    break;
                }
            }
        }
        $line ??= $this->firstWithRoom($lines, $key) ?? $lines[0];

        $role = $done < $required ? 'active' : 'spare';
        $after = $done + $quantity;
        $effects = [['type' => 'increment', 'job_item_id' => $line['id'], 'field' => $mode->counter(), 'quantity' => $quantity]];
        if ($isAsset) {
            $effects[] = ['type' => 'assign_asset', 'job_item_id' => $line['id'], 'asset_id' => $match['asset_id'], 'role' => $role, 'status' => $targetStatus];
            if ($mode === ScanMode::CheckOut) {
                $effects[] = ['type' => 'asset_status', 'asset_id' => $match['asset_id'], 'status' => 'checked_out'];
            } elseif ($match['status'] === 'available') {
                $effects[] = ['type' => 'asset_status', 'asset_id' => $match['asset_id'], 'status' => 'allocated'];
            }
        }

        $verb = $mode === ScanMode::Pick ? 'Picked' : 'Checked out';
        if ($after <= $required) {
            $message = "Active {$after} of {$required}";
            if ($after === $required && $pull > $required) {
                $message .= ' — all active units done, now pulling '.($pull - $required).' spare(s).';
            }
        } else {
            $message = 'Spare '.($after - $required).' of '.($pull - $required);
        }
        if ($after === $pull) {
            $message .= " · {$match['product']} complete ({$pull}/{$pull}).";
        }

        $needsPick = $mode === ScanMode::CheckOut && $isAsset && ! $this->wasPicked($lines, $match['asset_id']);

        $summaryLine = $line;
        $summaryLine[$key] = $line[$key] + $quantity;

        return new ScanResult(
            $needsPick ? ScanResult::WARNING : ScanResult::SUCCESS,
            $needsPick ? "{$verb} (not picked)" : "{$verb}: {$match['product']}",
            $needsPick ? "{$message} — this unit skipped the pick step." : $message,
            $effects,
            $this->summary($summaryLine, $after, $required, $pull),
            $item,
            $role,
        );
    }

    private function progress(ScanMode $mode, array $match, array $lines, array $item): ScanResult
    {
        $status = $mode->assetAssignmentStatus();
        if ($match['kind'] !== 'asset') {
            return new ScanResult(ScanResult::SUCCESS, $mode->label().': '.$match['product'], 'Bulk item confirmed.', [], $this->summary($lines[0]), $item);
        }
        foreach ($lines as $line) {
            $current = $line['assets'][$match['asset_id']] ?? null;
            if ($current === null) {
                continue;
            }
            if ((self::ORDER[$current] ?? 0) >= self::ORDER[$status]) {
                return ScanResult::warning('Already scanned', "{$match['asset_number']} is already ".str_replace('_', ' ', $current).'.', [], $this->summary($line), $item);
            }

            return new ScanResult(ScanResult::SUCCESS, $mode->label().': '.$match['asset_number'], "{$match['product']} marked {$status}.", [
                ['type' => 'assign_asset', 'job_item_id' => $line['id'], 'asset_id' => $match['asset_id'], 'role' => null, 'status' => $status],
            ], $this->summary($line), $item);
        }

        return ScanResult::warning('Not picked', "{$match['asset_number']} has not been picked for this job. Scan it in Pick mode first.", [], $this->summary($lines[0]), $item);
    }

    private function inbound(array $match, array $lines, int $quantity, array $item): ScanResult
    {
        $isAsset = $match['kind'] === 'asset';
        $out = array_sum(array_column($lines, 'checked_out'));
        $returned = array_sum(array_column($lines, 'returned'));

        $line = null;
        if ($isAsset) {
            foreach ($lines as $l) {
                $current = $l['assets'][$match['asset_id']] ?? null;
                if ($current === 'returned') {
                    return ScanResult::warning('Already returned', "{$match['asset_number']} was already checked in.", [], $this->summary($l), $item);
                }
                if ($current === 'checked_out') {
                    $line = $l;
                    break;
                }
            }
            if (! $line) {
                return ScanResult::warning('Not out on this job', "{$match['asset_number']} was not checked out on this job.", [], $this->summary($lines[0]), $item);
            }
        } else {
            if ($returned + $quantity > $out) {
                return ScanResult::warning('More than went out', "Only {$out} × {$match['product']} went out and {$returned} are back.", [], $this->summary($lines[0]), $item);
            }
            foreach ($lines as $l) {
                if ($l['checked_out'] - $l['returned'] > 0) {
                    $line = $l;
                    break;
                }
            }
            $line ??= $lines[0];
        }

        $effects = [['type' => 'increment', 'job_item_id' => $line['id'], 'field' => 'returned_quantity', 'quantity' => $quantity]];
        if ($isAsset) {
            $effects[] = ['type' => 'assign_asset', 'job_item_id' => $line['id'], 'asset_id' => $match['asset_id'], 'role' => null, 'status' => 'returned'];
            if (! in_array($match['status'], self::UNSERVICEABLE, true)) {
                $effects[] = ['type' => 'asset_status', 'asset_id' => $match['asset_id'], 'status' => 'available'];
            }
        }

        $back = $returned + $quantity;
        $summaryLine = $line;
        $summaryLine['returned'] = $line['returned'] + $quantity;

        return new ScanResult(
            ScanResult::SUCCESS,
            "Returned: {$match['product']}",
            "{$back} of {$out} back".($back === $out ? " · all {$match['product']} returned." : ''),
            $effects,
            $this->summary($summaryLine),
            $item,
        );
    }

    private function firstWithRoom(array $lines, string $key): ?array
    {
        foreach ($lines as $l) {
            if ($l[$key] < $l['required'] + $l['spare']) {
                return $l;
            }
        }

        return null;
    }

    private function wasPicked(array $lines, int $assetId): bool
    {
        foreach ($lines as $l) {
            $s = $l['assets'][$assetId] ?? null;
            if ($s !== null && (self::ORDER[$s] ?? 0) >= self::ORDER['picked']) {
                return true;
            }
        }

        return false;
    }

    private function describe(array $match): array
    {
        return array_filter([
            'kind' => $match['kind'],
            'product_id' => $match['product_id'],
            'product' => $match['product'],
            'asset_id' => $match['asset_id'] ?? null,
            'asset_number' => $match['asset_number'] ?? null,
            'status' => $match['status'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function summary(array $line, ?int $done = null, ?int $required = null, ?int $pull = null): array
    {
        return [
            'job_item_id' => $line['id'],
            'product' => $line['product'],
            'required' => $required ?? $line['required'],
            'spare' => $line['spare'],
            'pull' => $pull ?? ($line['required'] + $line['spare']),
            'picked' => $line['picked'],
            'checked_out' => $line['checked_out'],
            'returned' => $line['returned'],
            'done' => $done,
        ];
    }
}

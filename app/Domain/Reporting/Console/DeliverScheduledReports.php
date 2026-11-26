<?php

namespace App\Domain\Reporting\Console;

use App\Domain\Reporting\Models\ReportSchedule;
use App\Domain\Reporting\Services\ReportExporter;
use App\Domain\Reporting\Services\ReportSourceRegistry;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverScheduledReports extends Command
{
    protected $signature = 'reports:deliver-scheduled {--dry-run : List due schedules without sending}';

    protected $description = 'Run due report schedules and email the exports to their recipients';

    public function handle(ReportExporter $exporter, ReportSourceRegistry $registry): int
    {
        $now = Carbon::now();
        $due = ReportSchedule::query()
            ->with(['report.creator'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now))
            ->orderBy('id')
            ->get();

        $sent = 0;
        $failed = 0;
        foreach ($due as $schedule) {
            $report = $schedule->report;
            $owner = $report?->creator;
            if (! $report || ! $registry->has($report->source)) {
                $this->warn("Schedule #{$schedule->id}: report or source no longer exists; deactivated.");
                $schedule->forceFill(['is_active' => false])->save();

                continue;
            }
            // Deliver only what the report owner may still see/export.
            $permission = $registry->get($report->source)['permission'];
            if (! $owner || ! $owner->is_active_user() || ! $owner->can($permission) || ! $owner->can('reporting.reports.export')) {
                $this->warn("Schedule #{$schedule->id}: owner can no longer export \"{$report->name}\"; skipped.");
                $schedule->forceFill(['next_run_at' => $schedule->computeNextRun($now)])->save();

                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("Would deliver \"{$report->name}\" ({$schedule->format}) to ".implode(', ', $schedule->recipients ?? []));

                continue;
            }

            try {
                $file = $exporter->export($report, $schedule->format);
                $recipients = array_values(array_filter($schedule->recipients ?? [], fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
                if ($recipients !== []) {
                    $body = "Your scheduled report \"{$report->name}\" is attached ({$file['rows']} rows).\n\n"
                        .'Generated '.$now->format('M j, Y g:i A').' by '.config('app.name').'.';
                    Mail::raw($body, function (Message $message) use ($recipients, $report, $file) {
                        $message->to($recipients)
                            ->subject('Scheduled report: '.$report->name)
                            ->attachData($file['content'], $file['filename'], ['mime' => $file['mime']]);
                    });
                }
                $schedule->forceFill(['last_run_at' => $now, 'next_run_at' => $schedule->computeNextRun($now)])->save();
                $sent++;
                $this->info("Delivered \"{$report->name}\" to ".count($recipients).' recipient(s).');
            } catch (Throwable $e) {
                $failed++;
                Log::error('Scheduled report delivery failed', ['schedule' => $schedule->id, 'error' => $e->getMessage()]);
                $this->error("Schedule #{$schedule->id} failed: {$e->getMessage()}");
                $schedule->forceFill(['next_run_at' => $schedule->computeNextRun($now)])->save();
            }
        }

        $this->info("{$sent} report(s) delivered".($failed ? ", {$failed} failed" : '').'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

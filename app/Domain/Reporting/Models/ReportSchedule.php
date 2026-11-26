<?php

namespace App\Domain\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ReportSchedule extends Model
{
    protected $fillable = ['report_id', 'frequency', 'format', 'recipients', 'hour', 'day_of_week', 'day_of_month', 'last_run_at', 'next_run_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'is_active' => 'boolean',
            'hour' => 'integer',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** Next delivery time strictly after $from. */
    public function computeNextRun(?Carbon $from = null): Carbon
    {
        $from = ($from ?? Carbon::now())->copy();
        $candidate = $from->copy()->setTime($this->hour, 0);

        switch ($this->frequency) {
            case 'weekly':
                $candidate = $candidate->next((int) ($this->day_of_week ?? 1))->setTime($this->hour, 0);
                if ($from->dayOfWeek === (int) ($this->day_of_week ?? 1) && $from->copy()->setTime($this->hour, 0)->gt($from)) {
                    $candidate = $from->copy()->setTime($this->hour, 0);
                }
                break;
            case 'monthly':
                $day = min((int) ($this->day_of_month ?? 1), 28);
                $candidate = $from->copy()->setDay($day)->setTime($this->hour, 0);
                if ($candidate->lte($from)) {
                    $candidate = $candidate->addMonthNoOverflow();
                }
                break;
            default:
                if ($candidate->lte($from)) {
                    $candidate = $candidate->addDay();
                }
        }

        return $candidate;
    }
}

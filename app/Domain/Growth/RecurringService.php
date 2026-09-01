<?php

namespace App\Domain\Growth;

use App\Models\RecurringOccurrence;
use App\Models\RecurringSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Recurring service engine (doc 74): safe occurrence generation (idempotent by unique key).
 */
class RecurringService
{
    /** Generate upcoming occurrences up to horizon (default 30 days). Called by scheduler. */
    public function generateOccurrences(int $horizonDays = 30): int
    {
        $created = 0;
        $today = CarbonImmutable::today();
        $horizon = $today->addDays($horizonDays);

        $schedules = RecurringSchedule::where('status', 'active')
            ->whereDate('starts_on', '<=', $horizon)
            ->get();

        foreach ($schedules as $schedule) {
            $dates = $this->nextDates($schedule, $today, $horizon);

            foreach ($dates as $date) {
                $exists = RecurringOccurrence::where('schedule_id', $schedule->id)->whereDate('scheduled_on', $date)->exists();
                if ($exists) {
                    continue;
                }

                try {
                    RecurringOccurrence::create([
                        'schedule_id' => $schedule->id,
                        'scheduled_on' => $date,
                        'status' => 'pending',
                    ]);
                    $created++;
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // concurrent generator — safe to ignore (idempotency)
                }
            }

            $schedule->update(['last_generated_at' => now()]);
        }

        return $created;
    }

    /** Scheduler: convert due pending occurrences into draft orders. */
    public function materializeDueOccurrences(): int
    {
        $count = 0;

        $due = RecurringOccurrence::with('schedule.service')
            ->where('status', 'pending')
            ->whereDate('scheduled_on', '<=', CarbonImmutable::today()->addDays(2))
            ->get();

        foreach ($due as $occurrence) {
            DB::transaction(function () use ($occurrence, &$count) {
                $schedule = $occurrence->schedule;

                if ($schedule->status !== 'active') {
                    $occurrence->update(['status' => 'skipped']);

                    return;
                }

                $service = $schedule->service;
                if (! $service || $service->status !== 'active') {
                    $occurrence->update(['status' => 'skipped']);

                    return;
                }

                $order = app(\App\Domain\Order\OrderService::class)->createServiceOrder(
                    User::findOrFail($schedule->user_id),
                    $service,
                    [
                        'scheduled_at' => \Carbon\Carbon::parse($occurrence->scheduled_on)->setTimeFromTimeString($schedule->preferred_time ?? '09:00')->toIso8601String(),
                        'address_id' => $schedule->address_id,
                        'quantity' => 1,
                    ],
                );

                $occurrence->update(['status' => 'ordered', 'order_id' => $order->id]);
                $count++;
            });
        }

        return $count;
    }

    /** @return array<CarbonImmutable> */
    private function nextDates(RecurringSchedule $schedule, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $dates = [];
        $start = $from->max(CarbonImmutable::parse($schedule->starts_on));
        $cursor = $start->copy();

        while ($cursor->lte($until)) {
            $match = match ($schedule->frequency) {
                'weekly' => $schedule->day_of_week !== null && (int) $cursor->dayOfWeek === (int) $schedule->day_of_week,
                'monthly' => $schedule->day_of_month !== null && (int) $cursor->day === (int) $schedule->day_of_month,
                'quarterly' => $schedule->day_of_month !== null && (int) $cursor->day === (int) $schedule->day_of_month && in_array((int) $cursor->month, [1, 4, 7, 10], true),
                default => false,
            };

            if ($match && (!$schedule->ends_on || $cursor->lte(CarbonImmutable::parse($schedule->ends_on)))) {
                if ($schedule->occurrences_left === null || $schedule->occurrences_left > 0) {
                    $dates[] = $cursor->copy();
                }
            }

            $cursor = $cursor->addDay();
        }

        return $dates;
    }
}

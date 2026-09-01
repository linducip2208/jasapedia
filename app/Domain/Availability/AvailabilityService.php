<?php

namespace App\Domain\Availability;

use App\Domain\Auth\DomainException;
use App\Models\Partner;
use App\Models\PartnerBlock;
use App\Models\PartnerSchedule;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    private const BUFFER_MINUTES = 15;

    /** Generate bookable slots for a service+partner on a date. */
    public function slots(Partner $partner, Service $service, string $date): array
    {
        $day = CarbonImmutable::parse($date);
        $duration = $service->duration_minutes ?? 60;
        $total = $duration + self::BUFFER_MINUTES;

        $schedules = PartnerSchedule::where('partner_id', $partner->id)
            ->where('day_of_week', $day->dayOfWeek)
            ->get();

        $blocks = PartnerBlock::where('partner_id', $partner->id)
            ->where('starts_at', '<', $day->endOfDay())
            ->where('ends_at', '>', $day->startOfDay())
            ->get();

        $taken = $this->occupiedWindows($partner, $day);

        $slots = [];
        foreach ($schedules as $schedule) {
            $cursor = $day->setTimeFromTimeString($schedule->start_time->format('H:i'));
            $end = $day->setTimeFromTimeString($schedule->end_time->format('H:i'));

            while ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($end)) {
                $slotEnd = $cursor->copy()->addMinutes($total);

                if ($day->isToday() && $cursor->isPast()) {
                    $cursor = $cursor->addMinutes(30);

                    continue;
                }

                if ($this->overlapsBlock($cursor, $slotEnd, $blocks) || $this->overlapsTaken($cursor, $slotEnd, $taken)) {
                    $cursor = $cursor->addMinutes(30);

                    continue;
                }

                $slots[] = [
                    'starts_at' => $cursor->toIso8601String(),
                    'ends_at' => $cursor->copy()->addMinutes($duration)->toIso8601String(),
                    'duration_minutes' => $duration,
                ];

                $cursor = $cursor->addMinutes(30);
            }
        }

        return $slots;
    }

    /**
     * Atomically reserve a slot. Throws 409 on conflict.
     * Uses INSERT with unique constraint for race-safety.
     */
    public function reserveSlot(Partner $partner, Service $service, string $startsAt, ?int $orderId = null): int
    {
        $start = CarbonImmutable::parse($startsAt)->seconds(0);
        $duration = $service->duration_minutes ?? 60;

        $this->assertWithinSchedule($partner, $start, $duration);
        $this->assertNotBlocked($partner, $start, $duration);

        try {
            $id = DB::table('booking_slots')->insertGetId([
                'owner_type' => 'partner',
                'owner_id' => $partner->id,
                'scheduled_at' => $start,
                'duration_minutes' => $duration,
                'order_id' => $orderId,
                'status' => 'held',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Slot is no longer available.', 'SLOT_TAKEN', 409);
            }

            throw $e;
        }

        return $id;
    }

    public function releaseSlot(int $slotId): void
    {
        DB::table('booking_slots')->where('id', $slotId)->delete();
    }

    public function confirmSlot(int $slotId, int $orderId): void
    {
        DB::table('booking_slots')->where('id', $slotId)->update([
            'status' => 'confirmed', 'order_id' => $orderId, 'updated_at' => now(),
        ]);
    }

    public function saveSchedule(Partner $partner, array $weekly): void
    {
        DB::transaction(function () use ($partner, $weekly) {
            PartnerSchedule::where('partner_id', $partner->id)->delete();

            foreach ($weekly as $row) {
                if (! isset($row['start_time']) || ! isset($row['end_time'])) {
                    continue;
                }

                PartnerSchedule::create([
                    'partner_id' => $partner->id,
                    'day_of_week' => $row['day_of_week'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                ]);
            }
        });
    }

    public function addBlock(Partner $partner, array $data): PartnerBlock
    {
        return PartnerBlock::create([...$data, 'partner_id' => $partner->id]);
    }

    private function occupiedWindows(Partner $partner, CarbonImmutable $day): array
    {
        $rows = DB::table('booking_slots')
            ->where('owner_type', 'partner')
            ->where('owner_id', $partner->id)
            ->whereIn('status', ['held', 'confirmed'])
            ->whereBetween('scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->get(['scheduled_at', 'duration_minutes']);

        return $rows->map(fn ($r) => [
            'start' => CarbonImmutable::parse($r->scheduled_at),
            'end' => CarbonImmutable::parse($r->scheduled_at)->addMinutes($r->duration_minutes + self::BUFFER_MINUTES),
        ])->all();
    }

    private function overlapsBlock(CarbonImmutable $start, CarbonImmutable $end, $blocks): bool
    {
        foreach ($blocks as $block) {
            $blockStart = CarbonImmutable::parse($block->starts_at);
            $blockEnd = CarbonImmutable::parse($block->ends_at);

            if ($start->lt($blockEnd) && $end->gt($blockStart)) {
                return true;
            }
        }

        return false;
    }

    private function overlapsTaken(CarbonImmutable $start, CarbonImmutable $end, array $taken): bool
    {
        foreach ($taken as $window) {
            if ($start->lt($window['end']) && $end->gt($window['start'])) {
                return true;
            }
        }

        return false;
    }

    private function assertWithinSchedule(Partner $partner, CarbonImmutable $start, int $duration): void
    {
        $schedule = PartnerSchedule::where('partner_id', $partner->id)
            ->where('day_of_week', $start->dayOfWeek)
            ->first();

        if (! $schedule) {
            throw new DomainException('Partner is not working on this day.', 'OUT_OF_SCHEDULE', 409);
        }

        $end = $start->copy()->addMinutes($duration);
        $workStart = $start->copy()->setTimeFromTimeString($schedule->start_time->format('H:i'));
        $workEnd = $start->copy()->setTimeFromTimeString($schedule->end_time->format('H:i'));

        if ($start->lt($workStart) || $end->gt($workEnd)) {
            throw new DomainException('Requested time is outside working hours.', 'OUT_OF_SCHEDULE', 409);
        }
    }

    private function assertNotBlocked(Partner $partner, CarbonImmutable $start, int $duration): void
    {
        $blocked = PartnerBlock::where('partner_id', $partner->id)
            ->where('starts_at', '<', $start->copy()->addMinutes($duration))
            ->where('ends_at', '>', $start)
            ->exists();

        if ($blocked) {
            throw new DomainException('Partner is unavailable for this slot.', 'PARTNER_BLOCKED', 409);
        }
    }
}

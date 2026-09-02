<?php

namespace Database\Seeders\Demo;

use App\Support\Demo\DemoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Weekly partner schedules (needed by AvailabilityService for slot booking)
 * + occasional leave blocks. Booking slots themselves are created by the
 * order orchestrator through the domain service.
 */
class DemoAvailabilitySeeder extends Seeder
{
    public function run(DemoContext $ctx, array $partnerMap): void
    {
        $rows = [];
        foreach ($partnerMap as $i => $partner) {
            $withSunday = mt_rand(1, 100) <= 15;
            $openLate = mt_rand(1, 100) <= 30;

            $end = $openLate ? '17:00:00' : '15:00:00';

            for ($day = 1; $day <= 6; $day++) {
                $rows[] = [
                    'partner_id' => $partner['id'],
                    'day_of_week' => $day, // Mon..Sat
                    'start_time' => '08:00:00',
                    'end_time' => $end,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($withSunday) {
                $rows[] = [
                    'partner_id' => $partner['id'],
                    'day_of_week' => 0, // Sun
                    'start_time' => '09:00:00',
                    'end_time' => '13:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('partner_schedules')->insertOrIgnore($chunk);
        }

        // Leave blocks for ~3% of partners (future dates)
        $blocks = [];
        foreach ($partnerMap as $partner) {
            if (mt_rand(1, 100) <= 3) {
                $start = now()->addDays(mt_rand(3, 45))->setTime(0, 0);
                $blocks[] = [
                    'partner_id' => $partner['id'],
                    'type' => 'leave',
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addDays(mt_rand(1, 5))->setTime(23, 59),
                    'reason' => 'Cuti rutin tim',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($blocks, 500) as $chunk) {
            DB::table('partner_blocks')->insert($chunk);
        }
    }
}

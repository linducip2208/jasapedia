<?php

namespace Tests\Unit\Availability;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Auth\DomainException;
use App\Models\Partner;
use App\Models\PartnerSchedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CatalogSeeder::class);

        $this->partner = Partner::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'individual',
            'display_name' => 'Tech',
            'slug' => 'tech-'.uniqid(),
            'verification_state' => 'verified',
        ]);

        // Monday 08:00-12:00
        PartnerSchedule::create([
            'partner_id' => $this->partner->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->service = Service::create([
            'partner_id' => $this->partner->id,
            'category_id' => \App\Models\Category::first()->id,
            'title' => 'Servis', 'slug' => 'servis-'.uniqid(),
            'fulfillment_type' => 'per_unit', 'delivery_mode' => 'onsite',
            'price_model' => 'per_unit', 'base_price' => 100000,
            'duration_minutes' => 60, 'status' => 'active',
        ]);
    }

    public function test_slots_generated_within_hours(): void
    {
        $slots = app(AvailabilityService::class)->slots($this->partner, $this->service, 'next monday');

        $this->assertNotEmpty($slots);
        // 08:00-12:00, 60min slots, 30min step → 08:00..11:00 = 7 slots
        $this->assertCount(7, $slots);
    }

    public function test_slot_outside_schedule_rejected(): void
    {
        $this->expectException(DomainException::class);

        app(AvailabilityService::class)->reserveSlot(
            $this->partner,
            $this->service,
            \Carbon\Carbon::parse('next monday')->setTime(15, 0)->toIso8601String(),
        );
    }

    public function test_concurrent_same_slot_only_one_wins(): void
    {
        $slot = \Carbon\Carbon::parse('next monday')->setTime(9, 0)->toIso8601String();
        $svc = app(AvailabilityService::class);

        $first = $svc->reserveSlot($this->partner, $this->service, $slot);
        $this->assertGreaterThan(0, $first);

        $this->expectException(DomainException::class);
        $svc->reserveSlot($this->partner, $this->service, $slot);
    }

    public function test_unique_index_enforces_race_at_db_level(): void
    {
        $slot = \Carbon\Carbon::parse('next monday')->setTime(10, 0);
        $payload = [
            'owner_type' => 'partner', 'owner_id' => $this->partner->id,
            'scheduled_at' => $slot, 'duration_minutes' => 60, 'status' => 'held',
            'created_at' => now(), 'updated_at' => now(),
        ];

        DB::table('booking_slots')->insert($payload);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('booking_slots')->insert($payload);
    }

    public function test_after_release_slot_is_reusable(): void
    {
        $svc = app(AvailabilityService::class);
        $slot = \Carbon\Carbon::parse('next monday')->setTime(9, 30)->toIso8601String();

        $id = $svc->reserveSlot($this->partner, $this->service, $slot);
        $svc->releaseSlot($id);

        $id2 = $svc->reserveSlot($this->partner, $this->service, $slot);
        $this->assertGreaterThan(0, $id2);
    }
}

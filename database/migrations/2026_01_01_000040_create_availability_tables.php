<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sun .. 6=Sat
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->unique(['partner_id', 'day_of_week'], 'partner_schedule_unique');
        });

        Schema::create('partner_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // leave|holiday|blocked
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'starts_at', 'ends_at']);
        });

        /**
         * Concurrency-safe booking prevention (doc 20 §8):
         * unique (owner_type, owner_id, scheduled_at) — a race for the same slot
         * hits a DB unique violation. Also guarantees single active booking per
         * owner per start time.
         */
        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 24); // partner|organization
            $table->unsignedBigInteger('owner_id');
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes');
            $table->foreignId('order_id')->nullable()->index();
            $table->string('status', 16)->default('held'); // held|confirmed|released
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'scheduled_at'], 'booking_slot_unique');
            $table->index(['owner_type', 'owner_id', 'scheduled_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_slots');
        Schema::dropIfExists('partner_blocks');
        Schema::dropIfExists('partner_schedules');
    }
};

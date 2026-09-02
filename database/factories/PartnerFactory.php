<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\User;
use App\Support\Demo\DemoMediaPool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    private static bool $poolReady = false;

    public function definition(): array
    {
        $name = $this->faker->randomElement(['CV', 'PT']).' '.$this->faker->company();

        if (! self::$poolReady) {
            DemoMediaPool::ensurePool();
            self::$poolReady = true;
        }

        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['freelancer', 'individual', 'vendor_company']),
            'display_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'about' => $this->faker->paragraph(),
            'avatar_path' => DemoMediaPool::avatar($this->faker->numberBetween(0, DemoMediaPool::AVATAR_POOL_SIZE - 1)),
            'verification_state' => 'verified',
            'online_status' => 'offline',
            'city' => 'Jakarta Selatan',
            'is_demo' => true,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['verification_state' => 'verified']);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['verification_state' => 'unverified']);
    }

    public function company(): static
    {
        return $this->state(fn () => ['type' => 'vendor_company']);
    }
}

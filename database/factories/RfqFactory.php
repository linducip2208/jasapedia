<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RfqFactory extends Factory
{
    protected $model = Rfq::class;

    public function definition(): array
    {
        return [
            'code' => 'RFQ-'.now()->format('ymd').'-'.strtoupper(Str::random(5)).$this->faker->unique()->numberBetween(1, 999999),
            'user_id' => User::factory(),
            'category_id' => Category::query()->inRandomOrder()->value('id') ?? 1,
            'title' => 'Kebutuhan '.$this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'visibility' => 'public',
            'status' => 'open',
            'is_demo' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Partner;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        $overall = $this->faker->numberBetween(1, 5);

        return [
            'order_id' => Order::factory()->completed(),
            'author_id' => User::factory(),
            'partner_id' => Partner::factory(),
            'overall' => $overall,
            'dimension_ratings' => [
                'quality' => $overall,
                'communication' => $this->faker->numberBetween(1, 5),
                'value' => $this->faker->numberBetween(1, 5),
            ],
            'comment' => $this->faker->sentence(),
            'status' => 'published',
            'is_demo' => true,
        ];
    }
}

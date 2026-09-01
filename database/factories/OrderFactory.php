<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'type' => 'service',
            'status' => 'pending_payment',
            'fulfillment_type' => 'per_unit',
            'delivery_mode' => 'onsite',
            'subtotal' => 100000,
            'total' => 100000,
            'pricing_snapshot' => ['lines' => [], 'total' => 100000, 'currency' => 'IDR'],
        ];
    }
}

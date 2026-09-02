<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        $price = $this->faker->numberBetween(500000, 5000000);

        return [
            'code' => 'QUO-'.now()->format('ymd').'-'.strtoupper(Str::random(5)).$this->faker->unique()->numberBetween(1, 999999),
            'rfq_id' => Rfq::factory(),
            'partner_id' => Partner::factory(),
            'customer_id' => User::factory(),
            'version' => 1,
            'line_items' => [['name' => 'Pekerjaan sesuai lingkup', 'qty' => 1, 'unit_price' => $price, 'amount' => $price]],
            'subtotal' => $price,
            'tax' => 0,
            'discount' => 0,
            'total' => $price,
            'status' => 'sent',
            'is_demo' => true,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'decided_at' => now()]);
    }
}

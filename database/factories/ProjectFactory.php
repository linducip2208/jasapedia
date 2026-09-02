<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $title = 'Proyek '.$this->faker->words(4, true);

        return [
            'code' => 'PRJ-'.now()->format('ymd').'-'.strtoupper(Str::random(5)).$this->faker->unique()->numberBetween(1, 999999),
            'user_id' => User::factory(),
            'category_id' => Category::query()->inRandomOrder()->value('id') ?? 1,
            'title' => $title,
            'description' => $this->faker->paragraph(),
            'budget_type' => 'range',
            'budget_min' => 1000000,
            'budget_max' => 10000000,
            'visibility' => 'public',
            'status' => 'receiving_proposals',
            'is_demo' => true,
        ];
    }
}

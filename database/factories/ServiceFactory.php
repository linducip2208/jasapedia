<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Service;
use App\Support\Demo\DemoMediaPool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    private static bool $poolReady = false;

    public function definition(): array
    {
        $title = 'Service '.Str::title($this->faker->words(3, true));

        return [
            'partner_id' => Partner::factory(),
            'category_id' => Category::query()->inRandomOrder()->value('id') ?? 1,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'description' => $this->faker->paragraph(),
            'fulfillment_type' => 'appointment',
            'delivery_mode' => 'onsite',
            'price_model' => 'fixed',
            'base_price' => $this->faker->numberBetween(50000, 2000000),
            'duration_minutes' => 120,
            'status' => 'active',
            'media' => $this->media(),
            'is_demo' => true,
        ];
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }

    public function hourly(): static
    {
        return $this->state(fn () => ['price_model' => 'hourly', 'unit_label' => 'jam']);
    }

    public function perUnit(string $unit = 'unit'): static
    {
        return $this->state(fn () => ['price_model' => 'per_unit', 'unit_label' => $unit]);
    }

    /**
     * Cover + 2-5 gallery images from the local demo pool — factory-created
     * services are is_demo rows, so they must satisfy the same media invariant
     * as seeder-created ones (100% cover, no broken refs, no hotlinks).
     */
    private function media(): array
    {
        if (! self::$poolReady) {
            DemoMediaPool::ensurePool();
            self::$poolReady = true;
        }

        $slug = Category::query()->inRandomOrder()->value('slug') ?? 'handyman';
        $pool = DemoMediaPool::forCategory($slug);
        $idx = $this->faker->numberBetween(0, DemoMediaPool::COVERS_PER_CATEGORY - 1);

        $cover = $pool[$idx];
        $gallery = [$cover]; // cover doubles as first gallery item
        $target = $this->faker->numberBetween(2, 5);
        while (count($gallery) < $target) {
            $idx = ($idx + 5) % count($pool);
            if (! in_array($pool[$idx], $gallery, true)) {
                $gallery[] = $pool[$idx];
            }
        }

        return ['cover' => $cover, 'gallery' => $gallery];
    }
}

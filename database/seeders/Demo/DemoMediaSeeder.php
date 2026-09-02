<?php

namespace Database\Seeders\Demo;

use App\Support\Demo\DemoMediaPool;
use Illuminate\Database\Seeder;

/**
 * Generates the demo media pool into public/demo/** (idempotent, deterministic)
 * and syncs category icon keys. Runs BEFORE the other demo seeders so every
 * service/partner can reference existing assets.
 */
class DemoMediaSeeder extends Seeder
{
    public function run(): array
    {
        $result = DemoMediaPool::ensurePool();
        $iconsUpdated = DemoMediaPool::syncCategoryIcons();

        $this->command?->getOutput()->writeln(sprintf(
            '   Media pool: %d assets generated (covers %d, categories %d, avatars %d), icons synced: %d',
            $result['generated'],
            DemoMediaPool::COVERS_PER_CATEGORY,
            count(DemoMediaPool::ICON_KEYS),
            DemoMediaPool::AVATAR_POOL_SIZE,
            $iconsUpdated,
        ));

        return $result;
    }
}

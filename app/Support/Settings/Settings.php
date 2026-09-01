<?php

namespace App\Support\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Settings
{
    private const CACHE_KEY = 'system_settings';

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'group' => $group, 'updated_at' => now()],
        );
        Cache::forget(self::CACHE_KEY);
    }

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, static function () {
            return DB::table('system_settings')
                ->pluck('value', 'key')
                ->map(static fn ($v) => json_decode((string) $v, true))
                ->all();
        });
    }
}

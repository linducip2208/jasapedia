<?php

namespace App\Support\Demo;

use Illuminate\Support\Facades\DB;

/**
 * Deterministic local demo media pool.
 *
 * Generates (once, idempotent) into the PUBLIC web root:
 *   public/demo/services/<category-slug>/01..15.webp   (1200x800, ~100-300KB)
 *   public/demo/providers/av-01..24.svg                (initials avatar pool)
 *   public/demo/categories/<category-slug>.svg         (banner)
 *
 * 10.000 services reuse the pool deterministically — no 10k files, no
 * network hotlinks, works fully offline. Media paths stored in the
 * existing `media` JSON are relative and resolved by MediaService::url().
 */
final class DemoMediaPool
{
    public const COVERS_PER_CATEGORY = 15;

    public const AVATAR_POOL_SIZE = 24;

    private const WIDTH = 1200;

    private const HEIGHT = 800;

    /** Category icon keys (stored on categories.icon) for the icon component. */
    public const ICON_KEYS = [
        'technology-programming' => 'code',
        'design-creative' => 'pen-tool',
        'digital-marketing' => 'megaphone',
        'business-consulting' => 'briefcase',
        'accounting-tax' => 'calculator',
        'legal' => 'scale',
        'cleaning' => 'spray',
        'ac-electronics' => 'ac-unit',
        'plumbing' => 'pipe',
        'electrical' => 'zap',
        'handyman' => 'drill',
        'renovation' => 'paint-roller',
        'construction' => 'crane',
        'cctv-security' => 'cctv',
        'pest-control' => 'bug',
        'automotive' => 'car',
        'moving-logistics' => 'truck',
        'event-services' => 'stage',
        'photography' => 'camera',
        'education' => 'book',
        'personal-services' => 'spa',
    ];

    /** [dark, mid, light, accent] per category scene palette. */
    private const PALETTES = [
        'technology-programming' => ['#0f172a', '#1e293b', '#334155', '#14b8a6'],
        'design-creative' => ['#4c1d95', '#6d28d9', '#ede9fe', '#f59e0b'],
        'digital-marketing' => ['#7c2d12', '#c2410c', '#ffedd5', '#0d9488'],
        'business-consulting' => ['#0c4a6e', '#0369a1', '#e0f2fe', '#f59e0b'],
        'accounting-tax' => ['#064e3b', '#047857', '#d1fae5', '#fbbf24'],
        'legal' => ['#1e1b4b', '#312e81', '#e0e7ff', '#94a3b8'],
        'cleaning' => ['#0e7490', '#0891b2', '#cffafe', '#f59e0b'],
        'ac-electronics' => ['#334155', '#475569', '#e2e8f0', '#0ea5e9'],
        'plumbing' => ['#1e3a8a', '#1d4ed8', '#dbeafe', '#f97316'],
        'electrical' => ['#713f12', '#a16207', '#fef9c3', '#facc15'],
        'handyman' => ['#3f3f46', '#52525b', '#e4e4e7', '#f59e0b'],
        'renovation' => ['#7f1d1d', '#b91c1c', '#fee2e2', '#0d9488'],
        'construction' => ['#451a03', '#92400e', '#fef3c7', '#f59e0b'],
        'cctv-security' => ['#111827', '#1f2937', '#f3f4f6', '#22c55e'],
        'pest-control' => ['#14532d', '#166534', '#dcfce7', '#84cc16'],
        'automotive' => ['#0f172a', '#334155', '#cbd5e1', '#ef4444'],
        'moving-logistics' => ['#4a044e', '#86198f', '#fae8ff', '#f59e0b'],
        'event-services' => ['#831843', '#be185d', '#fce7f3', '#fbbf24'],
        'photography' => ['#18181b', '#27272a', '#fafafa', '#f59e0b'],
        'education' => ['#1e40af', '#1d4ed8', '#dbeafe', '#f59e0b'],
        'personal-services' => ['#701a75', '#a21caf', '#fae8ff', '#14b8a6'],
    ];

    /** Human label per category (painted on the cover). */
    private const LABELS = [
        'technology-programming' => 'Teknologi & Programming',
        'design-creative' => 'Design & Creative',
        'digital-marketing' => 'Digital Marketing',
        'business-consulting' => 'Business & Consulting',
        'accounting-tax' => 'Accounting & Tax',
        'legal' => 'Legal',
        'cleaning' => 'Cleaning Service',
        'ac-electronics' => 'AC & Electronics',
        'plumbing' => 'Plumbing',
        'electrical' => 'Electrical',
        'handyman' => 'Handyman',
        'renovation' => 'Renovation',
        'construction' => 'Construction',
        'cctv-security' => 'CCTV & Security',
        'pest-control' => 'Pest Control',
        'automotive' => 'Automotive',
        'moving-logistics' => 'Moving & Logistics',
        'event-services' => 'Event Services',
        'photography' => 'Photography',
        'education' => 'Education',
        'personal-services' => 'Personal Services',
    ];

    public static function slug(string $categorySlug): string
    {
        return preg_replace('/[^a-z0-9\-]/', '', $categorySlug);
    }

    /** Relative media paths (public web root) for a category pool. */
    public static function forCategory(string $categorySlug): array
    {
        $safe = self::slug($categorySlug);
        $paths = [];
        for ($i = 1; $i <= self::COVERS_PER_CATEGORY; $i++) {
            $paths[] = sprintf('demo/services/%s/%02d.webp', $safe, $i);
        }

        return $paths;
    }

    public static function avatar(int $index): string
    {
        return sprintf('demo/providers/av-%02d.svg', ($index % self::AVATAR_POOL_SIZE) + 1);
    }

    public static function categoryBanner(string $categorySlug): string
    {
        return 'demo/categories/'.self::slug($categorySlug).'.svg';
    }

    /** Generate every missing asset. Idempotent + deterministic. */
    public static function ensurePool(): array
    {
        $generated = 0;

        // 1. Service covers
        foreach (self::PALETTES as $slug => $palette) {
            $dir = public_path('demo/services/'.$slug);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            for ($i = 1; $i <= self::COVERS_PER_CATEGORY; $i++) {
                $file = sprintf('%s/%02d.webp', $dir, $i);
                if (is_file($file) && filesize($file) > 0) {
                    continue;
                }
                self::paintCover($slug, $palette, $i, $file);
                $generated++;
            }
        }

        // 2. Provider avatar pool (SVG initials — tiny, reusable)
        $avDir = public_path('demo/providers');
        if (! is_dir($avDir)) {
            mkdir($avDir, 0775, true);
        }
        $initials = ['AM', 'BS', 'CT', 'DR', 'EW', 'FN', 'GH', 'HR', 'IN', 'JS', 'KT', 'LM', 'MW', 'NP', 'OP', 'PR', 'QS', 'RT', 'SU', 'TV', 'UW', 'WX', 'YZ', 'DI'];
        $bgs = [['#0d9488', '#0f766e'], ['#d97706', '#b45309'], ['#4f46e5', '#4338ca'], ['#e11d48', '#be123c'], ['#0284c7', '#0369a1'], ['#059669', '#047857'], ['#7c3aed', '#6d28d9'], ['#475569', '#334155']];
        for ($i = 1; $i <= self::AVATAR_POOL_SIZE; $i++) {
            $file = sprintf('%s/av-%02d.svg', $avDir, $i);
            if (is_file($file)) {
                continue;
            }
            $pair = $bgs[($i - 1) % count($bgs)];
            $text = $initials[$i - 1];
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200" role="img" aria-label="{$text}">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="{$pair[0]}"/><stop offset="1" stop-color="{$pair[1]}"/>
  </linearGradient></defs>
  <rect width="200" height="200" fill="url(#g)"/>
  <circle cx="164" cy="28" r="52" fill="#ffffff" opacity="0.10"/>
  <circle cx="24" cy="176" r="64" fill="#ffffff" opacity="0.08"/>
  <text x="100" y="122" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="62" font-weight="800" fill="#ffffff" opacity="0.96">{$text}</text>
</svg>
SVG;
            file_put_contents($file, $svg);
            $generated++;
        }

        // 3. Category banners
        $catDir = public_path('demo/categories');
        if (! is_dir($catDir)) {
            mkdir($catDir, 0775, true);
        }
        foreach (self::PALETTES as $slug => $palette) {
            $file = $catDir.'/'.self::slug($slug).'.svg';
            if (is_file($file)) {
                continue;
            }
            $icon = self::ICON_PATHS[self::ICON_KEYS[$slug]] ?? self::ICON_PATHS['default'];
            $label = self::LABELS[$slug];
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360" role="img" aria-label="{$label}">
  <defs><linearGradient id="g" gradientTransform="rotate(18 0.5 0.5)">
    <stop offset="0" stop-color="{$palette[1]}"/><stop offset="1" stop-color="{$palette[0]}"/>
  </linearGradient></defs>
  <rect width="640" height="360" fill="url(#g)"/>
  <circle cx="520" cy="60" r="130" fill="{$palette[2]}" opacity="0.14"/>
  <circle cx="80" cy="320" r="90" fill="#ffffff" opacity="0.08"/>
  <g transform="translate(288,110) scale(2.7)" fill="none" stroke="{$palette[3]}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{$icon}</g>
  <text x="320" y="310" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="22" font-weight="700" fill="#ffffff" opacity="0.94" letter-spacing="1.5">{$label}</text>
</svg>
SVG;
            file_put_contents($file, $svg);
            $generated++;
        }

        return ['generated' => $generated, 'covers' => count(self::PALETTES) * self::COVERS_PER_CATEGORY];
    }

    /** Sync categories.icon keys (idempotent). */
    public static function syncCategoryIcons(): int
    {
        $n = 0;
        foreach (self::ICON_KEYS as $slug => $icon) {
            $n += DB::table('categories')->where('slug', $slug)->where(function ($q) use ($icon) {
                $q->whereNull('icon')->orWhere('icon', '!=', $icon);
            })->update(['icon' => $icon]);
        }

        return $n;
    }

    // ------------------------------------------------------------------
    //  Painting
    // ------------------------------------------------------------------

    private static function hex(string $hex, int $opacity = 100): int
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $opacity = max(0, min(100, $opacity));

        // GD alpha: 0 = opaque, 127 = transparent. $param is OPACITY percent.
        return imagecolorallocatealpha(self::$canvas, $r, $g, $b, (int) round(127 - $opacity * 1.27));
    }

    private static $canvas;

    private static function paintCover(string $slug, array $palette, int $index, string $file): void
    {
        [$dark, $mid, $light, $accent] = $palette;
        $w = self::WIDTH;
        $h = self::HEIGHT;

        $img = imagecreatetruecolor($w, $h);
        self::$canvas = $img;
        imagesavealpha($img, true);

        // Diagonal gradient dark → mid
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = self::lerpColor($dark, $mid, $t);
            imageline($img, 0, $y, $w, $y, $c);
        }

        // Soft light blobs (composition varies per index)
        $cx1 = 150 + ($index * 137) % 500;
        $cy1 = 80 + ($index * 89) % 200;
        imagefilledellipse($img, $cx1, $cy1, 620, 620, self::hex($light, 88));
        imagefilledellipse($img, $w - 180 - ($index * 61) % 260, $h - 140, 480, 480, self::hex($accent, 90));

        // Subtle diagonal stripes for texture
        imagesetthickness($img, 3);
        $stripes = self::hex($light, 93);
        for ($s = -2; $s < 16; $s++) {
            imageline($img, $s * 160, $h, $s * 160 + 400, 0, $stripes);
        }

        // Category scene
        self::scene($slug, $img, $index, $palette);

        // Label strip
        $label = self::LABELS[$slug];
        imagefilledrectangle($img, 48, $h - 96, 520, $h - 44, self::hex('#000000', 35));
        imagestring($img, 5, 64, $h - 82, strtoupper(substr($label, 0, 30)), self::hex('#ffffff'));

        // Index badge
        imagefilledrectangle($img, $w - 120, 40, $w - 48, 84, self::hex($accent, 20));
        imagestring($img, 5, $w - 106, 52, sprintf('%02d', $index), self::hex('#ffffff'));

        // Brand tag
        imagestring($img, 4, 64, 48, 'JASAPEDIA', self::hex($accent, 15));

        imagewebp($img, $file, 78);
        imagedestroy($img);
        self::$canvas = null;
    }

    private static function lerpColor(string $a, string $b, float $t): int
    {
        $a = ltrim($a, '#');
        $b = ltrim($b, '#');
        $r = (int) round(hexdec(substr($a, 0, 2)) + (hexdec(substr($b, 0, 2)) - hexdec(substr($a, 0, 2))) * $t);
        $g = (int) round(hexdec(substr($a, 2, 2)) + (hexdec(substr($b, 2, 2)) - hexdec(substr($a, 2, 2))) * $t);
        $bl = (int) round(hexdec(substr($a, 4, 2)) + (hexdec(substr($b, 4, 2)) - hexdec(substr($a, 4, 2))) * $t);

        return imagecolorallocate(self::$canvas, $r, $g, $bl);
    }

    private static function rrect(int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imagefilledrectangle(self::$canvas, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle(self::$canvas, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse(self::$canvas, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse(self::$canvas, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse(self::$canvas, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
        imagefilledellipse(self::$canvas, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
    }

    private static function thick(int $x1, int $y1, int $x2, int $y2, int $color, int $t = 6): void
    {
        imagesetthickness(self::$canvas, $t);
        imageline(self::$canvas, $x1, $y1, $x2, $y2, $color);
        imagesetthickness(self::$canvas, 1);
    }

    private static function scene(string $slug, $img, int $index, array $palette): void
    {
        [$dark, $mid, $light, $accent] = $palette;
        $white = self::hex('#ffffff');
        $accent = self::hex($accent);
        $soft = self::hex($accent, 40);
        $cx = 600 + ($index % 3) * 40 - 40; // slight per-index composition shift
        $cy = 380;

        switch ($slug) {
            case 'technology-programming':
                self::rrect($cx - 240, $cy - 160, 480, 300, 14, self::hex($dark, 10));
                self::rrect($cx - 200, $cy - 120, 400, 220, 8, self::hex('#0b1220', 5));
                foreach ([0, 1, 2, 3, 4, 5] as $line) {
                    $lw = [280, 180, 320, 220, 140, 260][$line];
                    imagefilledrectangle($img, $cx - 176, $cy - 88 + $line * 34, $cx - 176 + $lw, $cy - 74 + $line * 34, self::hex($line % 2 ? $light : $accent, $line % 2 ? 5 : 15));
                }
                self::rrect($cx - 280, $cy + 150, 560, 34, 12, self::hex($light, 8));
                break;

            case 'design-creative':
                foreach ([0, 1, 2] as $i) {
                    imagefilledellipse($img, $cx - 120 + $i * 120, $cy - 60, 90, 90, self::hex([$accent, $light, $mid][$i], $i === 1 ? 5 : 25));
                }
                imagefilledpolygon($img, [$cx - 40, $cy + 60, $cx + 60, $cy - 40, $cx + 100, $cy + 120, $cx, $cy + 150], self::hex($dark, 10));
                self::thick($cx + 60, $cy - 40, $cx + 100, $cy + 120, $accent, 10);
                self::thick($cx - 180, $cy + 150, $cx + 220, $cy + 150, $white, 4);
                break;

            case 'digital-marketing':
                imagefilledpolygon($img, [$cx - 200, $cy - 40, $cx - 40, $cy - 110, $cx - 40, $cy + 90, $cx - 200, $cy + 20], self::hex($light, 8));
                self::rrect($cx - 40, $cy - 130, 40, 260, 10, self::hex($accent, 15));
                for ($i = 0; $i < 3; $i++) {
                    imagearc($img, $cx + 30 + $i * 55, $cy - 20, 120 + $i * 90, 150 + $i * 90, 290, 70, $white);
                }
                foreach ([90, 150, 200] as $i => $bh) {
                    self::rrect($cx - 20 + $i * 70, $cy + 160 - $bh, 46, $bh, 8, self::hex($light, $i === 2 ? 20 : 8));
                }
                break;

            case 'business-consulting':
                self::rrect($cx - 200, $cy - 100, 400, 260, 18, self::hex($light, 8));
                self::rrect($cx - 70, $cy - 140, 140, 60, 12, self::hex($light, 8));
                imagefilledrectangle($img, $cx - 200, $cy + 10, $cx + 200, $cy + 22, self::hex($dark, 10));
                self::rrect($cx - 26, $cy - 6, 52, 40, 8, self::hex($accent, 15));
                break;

            case 'accounting-tax':
                self::rrect($cx - 140, $cy - 190, 280, 380, 16, self::hex($light, 8));
                self::rrect($cx - 108, $cy - 158, 216, 70, 8, self::hex($dark, 8));
                foreach ([0, 1, 2, 3] as $row) {
                    foreach ([0, 1, 2] as $col) {
                        self::rrect($cx - 108 + $col * 76, $cy - 64 + $row * 84, 60, 56, 8, self::hex($row * 3 + $col === 8 ? $accent : $white, $row * 3 + $col === 8 ? 15 : 40));
                    }
                }
                break;

            case 'legal':
                self::thick($cx, $cy - 180, $cx, $cy + 120, $white, 10);
                self::thick($cx - 190, $cy - 150, $cx + 190, $cy - 150, $white, 10);
                foreach ([-1, 1] as $side) {
                    self::thick($cx + $side * 190, $cy - 150, $cx + $side * 190, $cy - 90, $white, 6);
                    imagearc($img, $cx + $side * 190, $cy - 90, 150, 130, 0, 180, $white);
                    self::thick($cx + $side * 250, $cy - 88, $cx + $side * 130, $cy - 88, $white, 6);
                }
                self::rrect($cx - 90, $cy + 120, 180, 26, 8, $white);
                break;

            case 'cleaning':
                self::rrect($cx - 90, $cy - 100, 170, 240, 22, self::hex($light, 8));
                imagefilledpolygon($img, [$cx - 90, $cy - 100, $cx - 130, $cy - 160, $cx - 40, $cy - 160, $cx + 10, $cy - 110], self::hex($light, 8));
                self::rrect($cx + 6, $cy - 168, 90, 44, 10, self::hex($accent, 15));
                foreach ([[380, 240], [470, 190], [430, 320], [520, 290], [350, 160]] as $i => [$bx, $by]) {
                    imagefilledellipse($img, $bx + ($index % 3) * 18, $by, 26 + $i * 6, 26 + $i * 6, self::hex('#ffffff', 45));
                }
                break;

            case 'ac-electronics':
                self::rrect($cx - 260, $cy - 130, 520, 150, 16, self::hex($light, 8));
                self::rrect($cx - 220, $cy - 96, 240, 18, 8, self::hex($dark, 15));
                imagefilledellipse($img, $cx + 180, $cy - 55, 56, 56, self::hex($accent, 15));
                foreach ([-1, 0, 1] as $i) {
                    imagesetthickness($img, 8);
                    imagearc($img, $cx - 120 + $i * 60, $cy + 30 + $i * 14, 130, 170, 200, 340, self::hex('#ffffff', 30));
                }
                imagesetthickness($img, 1);
                break;

            case 'plumbing':
                self::thick($cx - 200, $cy + 60, $cx + 120, $cy + 60, self::hex($light, 8), 56);
                self::thick($cx + 120, $cy + 60, $cx + 120, $cy - 140, self::hex($light, 8), 56);
                self::rrect($cx - 230, $cy + 32, 60, 56, 8, $white);
                self::rrect($cx + 92, $cy - 170, 56, 60, 8, $white);
                imagefilledpolygon($img, [$cx - 190, $cy - 90, $cx - 158, $cy - 40, $cx - 222, $cy - 40], self::hex($accent, 15));
                imagefilledellipse($img, $cx - 190, $cy - 20, 44, 44, self::hex($accent, 15));
                break;

            case 'electrical':
                self::rrect($cx - 170, $cy - 170, 340, 340, 18, self::hex($light, 8));
                foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as [$a, $b]) {
                    imagefilledellipse($img, $cx - 70 + $a * 140, $cy - 70 + $b * 140, 44, 80, self::hex($dark, 10));
                }
                imagefilledpolygon($img, [$cx + 190, $cy - 170, $cx + 120, $cy - 10, $cx + 170, $cy - 10, $cx + 110, $cy + 130, $cx + 210, $cy - 40, $cx + 160, $cy - 40], self::hex($accent, 12));
                break;

            case 'handyman':
                self::rrect($cx - 200, $cy - 80, 240, 110, 14, self::hex($light, 8));
                self::rrect($cx + 40, $cy - 60, 60, 70, 6, $white);
                self::thick($cx + 100, $cy - 25, $cx + 190, $cy - 25, $accent, 14);
                imagefilledpolygon($img, [$cx - 140, $cy + 30, $cx - 40, $cy + 30, $cx - 80, $cy + 150, $cx - 180, $cy + 150], self::hex($accent, 20));
                self::thick($cx + 150, $cy - 110, $cx + 230, $cy + 40, $white, 12);
                break;

            case 'renovation':
                for ($i = 0; $i < 3; $i++) {
                    imagefilledrectangle($img, $cx - 240 + $i * 80, $cy - 180, $cx - 200 + $i * 80, $cy + 180, self::hex($light, 60 - $i * 12));
                }
                self::rrect($cx + 60, $cy - 150, 220, 60, 14, self::hex($accent, 20));
                self::thick($cx + 170, $cy - 90, $cx + 170, $cy + 20, $white, 14);
                self::rrect($cx + 130, $cy + 20, 80, 44, 8, $white);
                self::thick($cx + 170, $cy + 64, $cx + 170, $cy + 150, $white, 10);
                break;

            case 'construction':
                self::thick($cx - 60, $cy + 160, $cx - 60, $cy - 180, $white, 16);
                self::thick($cx - 220, $cy - 150, $cx + 200, $cy - 180, $white, 12);
                self::thick($cx - 200, $cy - 150, $cx - 60, $cy - 40, self::hex($accent, 20), 6);
                self::thick($cx + 150, $cy - 178, $cx + 150, $cy - 60, $white, 6);
                self::rrect($cx + 120, $cy - 60, 60, 60, 6, self::hex($accent, 15));
                foreach ([0, 1, 2] as $i) {
                    self::rrect($cx - 230 + $i * 62, $cy + 120 - $i * 40, 180 - $i * 30, 36, 4, self::hex($light, 30));
                }
                break;

            case 'cctv-security':
                imagefilledpolygon($img, [$cx - 220, $cy - 80, $cx + 120, $cy - 160, $cx + 160, $cy - 40, $cx - 180, $cy + 40], self::hex($light, 8));
                imagefilledellipse($img, $cx + 90, $cy - 100, 74, 74, self::hex($accent, 12));
                imagefilledpolygon($img, [$cx + 130, $cy - 40, $cx + 340, $cy + 120, $cx + 300, $cy + 260, $cx + 60, $cy + 60], self::hex($accent, 55));
                self::rrect($cx - 280, $cy + 120, 140, 90, 10, self::hex($dark, 10));
                imagefilledellipse($img, $cx - 210, $cy + 165, 30, 30, self::hex($accent, 12));
                break;

            case 'pest-control':
                self::rrect($cx - 190, $cy - 110, 130, 250, 18, self::hex($light, 8));
                self::rrect($cx - 160, $cy - 160, 70, 50, 8, $white);
                imagefilledellipse($img, $cx + 160, $cy + 20, 130, 90, self::hex($dark, 10));
                imagefilledellipse($img, $cx + 160, $cy - 40, 60, 60, self::hex($dark, 10));
                for ($i = 0; $i < 3; $i++) {
                    self::thick($cx + 100 - $i * 8, $cy - 10 + $i * 30, $cx + 30 - $i * 8, $cy + 10 + $i * 30, self::hex($dark, 10), 6);
                    self::thick($cx + 220 + $i * 8, $cy - 10 + $i * 30, $cx + 290 + $i * 8, $cy + 10 + $i * 30, self::hex($dark, 10), 6);
                }
                break;

            case 'automotive':
                imagefilledpolygon($img, [$cx - 280, $cy + 40, $cx - 200, $cy - 60, $cx - 60, $cy - 100, $cx + 120, $cy - 90, $cx + 250, $cy + 10, $cx + 260, $cy + 60], self::hex($light, 8));
                imagefilledpolygon($img, [$cx - 140, $cy - 70, $cx - 50, $cy - 90, $cx + 60, $cy - 85, $cx + 90, $cy - 20, $cx - 150, $cy - 15], self::hex($dark, 10));
                foreach ([-140, 140] as $wx) {
                    imagefilledellipse($img, $cx + $wx, $cy + 70, 110, 110, self::hex('#000000', 15));
                    imagefilledellipse($img, $cx + $wx, $cy + 70, 52, 52, $white);
                }
                self::rrect($cx + 200, $cy - 10, 60, 20, 6, self::hex($accent, 15));
                break;

            case 'moving-logistics':
                self::rrect($cx - 300, $cy - 120, 260, 200, 10, self::hex($light, 8));
                self::thick($cx - 170, $cy - 120, $cx - 170, $cy + 80, self::hex($dark, 12), 8);
                imagefilledpolygon($img, [$cx + 10, $cy - 60, $cx + 150, $cy - 60, $cx + 220, $cy + 20, $cx + 220, $cy + 80, $cx + 10, $cy + 80], self::hex($accent, 18));
                foreach ([[40, 30], [110, 30], [40, 100], [110, 100]] as [$bx, $by]) {
                    self::rrect($cx + $bx, $cy + $by - 60, 54, 48, 6, self::hex($light, 30));
                }
                foreach ([-170, 130] as $wx) {
                    imagefilledellipse($img, $cx + $wx, $cy + 100, 80, 80, self::hex('#000000', 15));
                    imagefilledellipse($img, $cx + $wx, $cy + 100, 36, 36, $white);
                }
                break;

            case 'event-services':
                imagefilledpolygon($img, [$cx - 260, $cy - 60, $cx, $cy - 220, $cx + 260, $cy - 60], self::hex($light, 8));
                self::rrect($cx - 200, $cy - 40, 400, 180, 10, self::hex($dark, 10));
                foreach ([0, 1, 2, 3, 4] as $i) {
                    imagefilledellipse($img, $cx - 220 + $i * 110, $cy - 100 - (($i * 37) % 30), 26, 26, self::hex($accent, 20 - $i * 3));
                }
                self::thick($cx - 260, $cy - 80, $cx + 260, $cy - 80, $white, 5);
                self::rrect($cx - 90, $cy + 150, 180, 50, 10, self::hex($accent, 20));
                break;

            case 'photography':
                self::rrect($cx - 230, $cy - 130, 460, 280, 22, self::hex($light, 8));
                self::rrect($cx - 70, $cy - 170, 140, 50, 10, self::hex($light, 8));
                imagefilledellipse($img, $cx, $cy + 10, 170, 170, self::hex($dark, 10));
                imagefilledellipse($img, $cx, $cy + 10, 110, 110, self::hex($accent, 20));
                imagefilledellipse($img, $cx, $cy + 10, 50, 50, $white);
                self::rrect($cx + 140, $cy - 100, 60, 34, 6, self::hex($accent, 15));
                break;

            case 'education':
                imagefilledpolygon($img, [$cx, $cy - 60, $cx - 260, $cy - 120, $cx - 260, $cy + 120, $cx, $cy + 170], self::hex($light, 8));
                imagefilledpolygon($img, [$cx, $cy - 60, $cx + 260, $cy - 120, $cx + 260, $cy + 120, $cx, $cy + 170], self::hex($light, 20));
                self::thick($cx, $cy - 60, $cx, $cy + 170, $white, 8);
                foreach ([0, 1, 2] as $i) {
                    self::thick($cx - 220 + $i * 8, $cy - 70 + $i * 62, $cx - 60, $cy - 56 + $i * 62, self::hex($dark, 12), 6);
                    self::thick($cx + 60, $cy - 56 + $i * 62, $cx + 220 - $i * 8, $cy - 70 + $i * 62, self::hex($dark, 12), 6);
                }
                imagefilledpolygon($img, [$cx + 190, $cy - 210, $cx + 240, $cy - 160, $cx + 150, $cy - 60, $cx + 100, $cy - 110], self::hex($accent, 15));
                break;

            case 'personal-services':
                self::rrect($cx - 110, $cy - 90, 160, 250, 26, self::hex($light, 8));
                self::rrect($cx - 80, $cy - 150, 100, 60, 10, self::hex($light, 8));
                self::rrect($cx - 60, $cy - 210, 40, 60, 8, $white);
                self::rrect($cx - 78, $cy - 20, 120, 120, 12, self::hex($accent, 20));
                imagefilledellipse($img, $cx + 180, $cy + 60, 120, 120, self::hex($accent, 45));
                imagefilledellipse($img, $cx + 220, $cy - 20, 60, 60, self::hex($accent, 45));
                break;

            default:
                imagefilledellipse($img, $cx, $cy, 260, 260, self::hex($light, 10));
        }
    }

    /** Inline SVG inner paths for category icons (24x24 stroke style). */
    private const ICON_PATHS = [
        'default' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/>',
        'code' => '<path d="M8 9l-4 3 4 3M16 9l4 3-4 3M13 5l-2 14"/>',
        'pen-tool' => '<path d="M12 19l7-7-4-4-7 7-1 5z"/><path d="M15 8l1-5 4 4-5 1"/>',
        'megaphone' => '<path d="M3 11l16-7-4 16-5-5z"/><path d="M10 15l-2 6"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'calculator' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"/>',
        'scale' => '<path d="M12 3v18M5 7h14M7 7l-3 7a4 4 0 0 0 6 0zM17 7l-3 7a4 4 0 0 0 6 0z"/>',
        'spray' => '<path d="M9 3v6M6 9h6l1 12H5zM18 3l3 3-9 9-3-3z"/>',
        'ac-unit' => '<rect x="3" y="5" width="18" height="7" rx="1"/><path d="M6 16c1-1 3-1 4 0s3 1 4 0 3-1 4 0M6 19c1-1 3-1 4 0"/>',
        'pipe' => '<path d="M14 7a3 3 0 1 1 4 4l-6 6a4 4 0 0 1-6-6z"/><path d="M12 9l3 3"/>',
        'zap' => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
        'drill' => '<path d="M14 7l3-3 3 3-3 3M3 21l8-8M17 13l4 4-4 4-4-4"/>',
        'paint-roller' => '<rect x="3" y="4" width="12" height="6" rx="1"/><path d="M15 7h4v4h-5v3M14 14v6"/>',
        'crane' => '<path d="M5 21V6l10-3M5 9h14M15 6v4"/><path d="M15 10v5a2 2 0 0 0 4 0v-1"/>',
        'cctv' => '<path d="M3 8l13-4 2 6-13 4zM7 13v4a2 2 0 0 0 2 2h4"/><circle cx="19" cy="15" r="2"/>',
        'bug' => '<circle cx="12" cy="9" r="4"/><path d="M8 9H3M21 9h-5M9 13l-4 6M15 13l4 6M12 13v6"/>',
        'car' => '<path d="M5 13l1.5-4A2 2 0 0 1 8.4 8h7.2a2 2 0 0 1 1.9 1.3L19 13"/><rect x="3" y="13" width="18" height="5" rx="1"/><circle cx="7" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/>',
        'truck' => '<rect x="1" y="7" width="13" height="9" rx="1"/><path d="M14 10h4l3 3v3h-7"/><circle cx="6" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/>',
        'stage' => '<path d="M4 21V9l8-6 8 6v12"/><path d="M4 13h16"/><circle cx="12" cy="6" r="1"/>',
        'camera' => '<rect x="2" y="7" width="20" height="13" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 7l2-3h4l2 3"/>',
        'book' => '<path d="M2 8l10-5 10 5-10 5z"/><path d="M6 10v5c0 2 12 2 12 0v-5M22 8v6"/>',
        'spa' => '<circle cx="12" cy="6" r="3"/><path d="M12 9v6M8 21c0-3 1.8-6 4-6s4 3 4 6"/>',
    ];
}

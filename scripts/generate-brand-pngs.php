<?php
// One-off brand PNG generator — draws the Jasapedia mark natively with GD.
$palette = ['bg' => [13, 148, 136], 'ink' => [15, 118, 110], 'white' => [255, 255, 255], 'amber' => [245, 158, 11]];

function drawMark(int $size, bool $withBg, array $p): GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $scale = $size / 512;
    $cx = 256 * $scale;
    $cy = 256 * $scale;
    $r = 124 * $scale;
    $w = 34 * $scale;

    if ($withBg) {
        $bg = imagecolorallocate($img, ...$p['bg']);
        $rx = 120 * $scale;
        imagefilledroundrect($img, 0, 0, $size, $size, $rx, $bg);
    }

    $arc = $withBg ? imagecolorallocate($img, ...$p['white']) : imagecolorallocate($img, ...$p['ink']);
    // five 90-degree arc segments, broken at gaps (mimic SVG rounded arc gaps)
    $gaps = [[98, 185], [8, 95], [188, 275], [278, 365], [368, 92]];
    // Instead: draw full circle arc ring as segments matching SVG paths
    $segments = [[8, 90], [98, 180], [188, 270], [278, 350], [358, 84]];
    foreach ($segments as [$start, $end]) {
        // GD arcs are clockwise from 3 o'clock; our SVG starts near top (12 o'clock = 270 in GD terms with y-down).
        imagearc($img, (int) $cx, (int) $cy, (int) ($r * 2), (int) ($r * 2), 270 + $start, 270 + $end, $arc);
    }
    // thicken by drawing slightly offset arcs
    $off = (int) ($w / 2);
    foreach ($segments as [$start, $end]) {
        imagearc($img, (int) $cx, (int) ($cy - $off), (int) ($r * 2), (int) ($r * 2), 270 + $start, 270 + $end, $arc);
        imagearc($img, (int) $cx, (int) ($cy + $off), (int) ($r * 2), (int) ($r * 2), 270 + $start, 270 + $end, $arc);
        imagearc($img, (int) ($cx - $off), (int) $cy, (int) ($r * 2), (int) ($r * 2), 270 + $start, 270 + $end, $arc);
        imagearc($img, (int) ($cx + $off), (int) $cy, (int) ($r * 2), (int) ($r * 2), 270 + $start, 270 + $end, $arc);
    }

    $amber = imagecolorallocate($img, ...$p['amber']);
    $dotR = 52 * $scale;
    imagefilledellipse($img, (int) $cx, (int) $cy, (int) ($dotR * 2), (int) ($dotR * 2), $amber);

    return $img;
}

function imagefilledroundrect(GdImage $img, int $x, int $y, int $w, int $h, int $r, int $color): void
{
    imagefilledrectangle($img, $x + $r, $y, $x + $w - $r - 1, $y + $h - 1, $color);
    imagefilledrectangle($img, $x, $y + $r, $x + $w - 1, $y + $h - $r - 1, $color);
    imagefilledarc($img, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
    imagefilledarc($img, $x + $w - $r - 1, $y + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE);
    imagefilledarc($img, $x + $w - $r - 1, $y + $h - $r - 1, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE);
    imagefilledarc($img, $x + $r, $y + $h - $r - 1, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE);
}

$sizes = [
    ['public/branding/favicon-32x32.png', 32, true],
    ['public/branding/favicon-192x192.png', 192, true],
    ['public/branding/apple-touch-icon.png', 180, true],
    ['public/branding/icon-512x512.png', 512, true],
    ['public/branding/logo-mark.png', 256, false],
    ['public/branding/og-default.png', 0, true], // OG handled below
];

foreach ($sizes as [$path, $size, $bg]) {
    if ($size === 0) {
        continue;
    }
    $img = drawMark($size, $bg, $palette);
    imagepng($img, __DIR__.'/../'.$path);
    imagedestroy($img);
    echo "wrote $path\n";
}

// OG image 1200x630: teal background, mark, wordmark text
$w = 1200;
$h = 630;
$img = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($img, 13, 148, 136);
$deep = imagecolorallocate($img, 17, 94, 89);
imagefilledrectangle($img, 0, 0, $w, $h, $bg);
for ($i = 0; $i < $h; $i++) {
    $t = $i / $h;
    $c = imagecolorallocate($img, (int) (13 - 4 * $t), (int) (148 - 54 * $t), (int) (136 - 47 * $t));
    imageline($img, 0, $i, $w, $i, $c);
}
$mark = drawMark(280, false, $palette);
imagecopy($img, $mark, 110, 175, 0, 0, 280, 280);
imagedestroy($mark);

$font = 'C:\\Windows\\Fonts\\arialbd.ttf';
$white = imagecolorallocate($img, 255, 255, 255);
$amber = imagecolorallocate($img, 245, 158, 11);
if (is_file($font)) {
    imagettftext($img, 84, 0, 440, 300, $white, $font, 'Jasapedia');
    imagettftext($img, 34, 0, 444, 370, $amber, $font, 'Semua Jasa, Satu Platform');
} else {
    imagestring($img, 5, 440, 270, 'Jasapedia', $white);
    imagestring($img, 4, 440, 340, 'Semua Jasa, Satu Platform', $amber);
}
imagepng($img, __DIR__.'/../public/branding/og-default.png');
imagedestroy($img);
echo "wrote og-default.png\n";

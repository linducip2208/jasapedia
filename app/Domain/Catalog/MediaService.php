<?php

namespace App\Domain\Catalog;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Storage-agnostic media handling. Disk configurable: MEDIA_DISK (public|s3|r2).
 * Validation uses real MIME detection — never trusts the client extension.
 */
class MediaService
{
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Store an image. Returns stored path (relative to disk root). */
    public function storeImage(UploadedFile $file, string $directory, int $maxSizeKb = 4096): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Upload tidak valid.');
        }

        // Real MIME detection from content (finfo), not client extension.
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException('Format file harus JPG, PNG, atau WebP.');
        }

        if ($file->getSize() > $maxSizeKb * 1024) {
            throw new RuntimeException("Ukuran maksimal {$maxSizeKb}KB.");
        }

        // Double-check magic bytes for common formats.
        $this->assertMagicBytes($file);

        $path = $file->store($directory, config('services.media.disk', 'public'));

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan file.');
        }

        return $path;
    }

    public function storeServiceGallery(array $files): array
    {
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $this->storeImage($file, 'services/'.now()->format('Y/m'));
        }

        return $paths;
    }

    public function url(string $path): string
    {
        // Static demo assets ship in the public web root (public/demo/**),
        // not on the storage disk — serve them as-is.
        if (str_starts_with($path, 'demo/')) {
            return asset($path);
        }

        return Storage::disk(config('services.media.disk', 'public'))->url($path);
    }

    private function assertMagicBytes(UploadedFile $file): void
    {
        $handle = fopen($file->getRealPath(), 'rb');
        try {
            $bytes = fread($handle, 12) ?: '';
        } finally {
            fclose($handle);
        }

        $jpeg = str_starts_with($bytes, "\xFF\xD8\xFF");
        $png = str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
        $webp = str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 8, 4), 'WEBP');

        if (! ($jpeg || $png || $webp)) {
            throw new RuntimeException('Isi file bukan gambar yang valid.');
        }
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/** Зображення бувають завантаженими файлами (storage/public) або зовнішніми URL зі старого сайту */
trait HasMedia
{
    public static function mediaUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }
        if (preg_match('~^https?://~i', $path)) {
            return preg_replace('~^http://(www\.)?tourism\.gov\.ua~', 'https://www.tourism.gov.ua', $path);
        }

        return Storage::disk('public')->url($path);
    }

    public static function mediaUrls(?array $paths): array
    {
        return collect($paths ?? [])->map(fn ($p) => static::mediaUrl($p))->filter()->values()->all();
    }
}

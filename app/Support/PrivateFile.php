<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Soubory od uživatelů (dokumenty, fotky, přílohy) leží jen na privátním disku a stahují se autorizovanou routou.
 */
final class PrivateFile
{
    public const string DISK = 'local';

    /** Whitelist obrázků od uživatelů (žádné SVG). */
    public const string IMAGE_MIMES = 'mimes:jpg,jpeg,png,webp';

    public const int IMAGE_MAX_KB = 5120;

    /**
     * Stažení jako příloha s nosniff, aby prohlížeč soubor nikdy nevykreslil jako HTML.
     */
    public static function download(string $path, string $name): StreamedResponse
    {
        if (! Storage::disk(self::DISK)->exists($path)) {
            abort(404);
        }

        return Storage::disk(self::DISK)->download($path, $name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

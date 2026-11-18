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
     * Stažení jako příloha s nosniff a sandbox CSP, aby prohlížeč soubor nikdy nevykreslil jako HTML.
     */
    public static function download(string $path, string $name): StreamedResponse
    {
        if (! Storage::disk(self::DISK)->exists($path)) {
            abort(404);
        }

        return Storage::disk(self::DISK)->download($path, self::downloadName($path, $name), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /**
     * Jméno souboru ke stažení skládá server: bez lomítek a řídicích znaků (Symfony je v hlavičce nepřijme)
     * a s příponou uloženého souboru.
     */
    public static function downloadName(string $path, string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace(['/', '\\'], '-', $name)));
        $extension = self::extension($path);

        if ($name === '') {
            return basename($path);
        }
        if ($extension !== '' && ! str_ends_with(mb_strtolower($name), '.'.$extension)) {
            $name .= '.'.$extension;
        }

        return $name;
    }

    public static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

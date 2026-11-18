<?php

namespace App\Console\Commands;

use App\Support\PrivateFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Jednorázový přesun souborů od uživatelů z veřejného disku na privátní (krok 2.3). Spustit při nasazení,
 * opakované spuštění nic nezmění.
 */
class MoveFilesToPrivate extends Command
{
    protected $signature = 'rentflow:move-files-to-private';

    protected $description = 'Move user files (documents, property and ticket images, comment attachments) to the private disk';

    /** Adresáře s uživatelskými soubory – přesouvá se celý obsah, i soubory bez záznamu v DB. */
    private const array DIRECTORIES = ['documents', 'properties', 'tickets'];

    /** Tabulka => sloupec s cestou k souboru (kontrola, že po přesunu nic nechybí). */
    private const array FILE_COLUMNS = [
        'documents' => 'file_path',
        'property_images' => 'image_path',
        'ticket_images' => 'image_path',
        'ticket_comments' => 'attachment_path',
    ];

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk(PrivateFile::DISK);
        $moved = 0;

        foreach (self::DIRECTORIES as $directory) {
            foreach ($public->allFiles($directory) as $path) {
                if (! $private->exists($path)) {
                    $private->put($path, $public->get($path));
                }
                $public->delete($path);
                $moved++;
            }
            $public->deleteDirectory($directory);
        }

        // PDF smluv se generují za běhu, uložené kopie s osobními údaji pryč.
        $public->deleteDirectory('contracts');
        $contracts = DB::table('leases')->whereNotNull('contract_path')->update(['contract_path' => null]);

        $missing = 0;
        foreach (self::FILE_COLUMNS as $table => $column) {
            foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $path) {
                if (! $private->exists($path)) {
                    $missing++;
                    $this->warn("Missing file: {$table}.{$column} = {$path}");
                }
            }
        }

        $this->info("Moved: {$moved}, missing: {$missing}");
        $this->info("Contract PDFs removed, leases cleared: {$contracts}");

        return self::SUCCESS;
    }
}

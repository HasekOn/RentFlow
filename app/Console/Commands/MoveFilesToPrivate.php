<?php

namespace App\Console\Commands;

use App\Support\PrivateFile;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Jednorázový přesun souborů od uživatelů z veřejného disku na privátní (krok 2.3). Spustit při nasazení,
 * opakované spuštění nic nezmění. Veřejný soubor se smaže, jen když je na privátním disku stejná kopie.
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

    /** @var array{moved: int, already: int, conflicts: int, failed: int} */
    private array $counts;

    public function handle(): int
    {
        $this->counts = ['moved' => 0, 'already' => 0, 'conflicts' => 0, 'failed' => 0];
        $public = Storage::disk('public');
        $private = Storage::disk(PrivateFile::DISK);

        foreach (self::DIRECTORIES as $directory) {
            foreach ($public->allFiles($directory) as $path) {
                $this->moveFile($public, $private, $path);
            }
            if ($public->allFiles($directory) === []) {
                $public->deleteDirectory($directory);
            }
        }

        // PDF smluv se generují za běhu, uložené kopie s osobními údaji pryč.
        $public->deleteDirectory('contracts');
        $contracts = DB::table('leases')->whereNotNull('contract_path')->update(['contract_path' => null]);

        $missing = $this->countMissing($private);

        $this->info("Moved: {$this->counts['moved']}, already private: {$this->counts['already']}, "
            ."conflicts: {$this->counts['conflicts']}, failed: {$this->counts['failed']}, missing: {$missing}");
        $this->info("Contract PDFs removed, leases cleared: {$contracts}");

        return $this->counts['conflicts'] + $this->counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function moveFile(FilesystemAdapter $public, FilesystemAdapter $private, string $path): void
    {
        try {
            if ($private->exists($path)) {
                if ($private->checksum($path) !== $public->checksum($path)) {
                    $this->counts['conflicts']++;
                    $this->warn("Conflict, different private file kept, public file left in place: {$path}");

                    return;
                }
                $this->counts['already']++;
            } else {
                $stream = $public->readStream($path);
                $written = $stream !== null && $private->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (! $written || $private->checksum($path) !== $public->checksum($path)) {
                    throw new RuntimeException('write failed');
                }
                $this->counts['moved']++;
            }

            $public->delete($path);
        } catch (Throwable $e) {
            $this->counts['failed']++;
            $this->error("Failed, public file left in place: {$path} ({$e->getMessage()})");
        }
    }

    private function countMissing(FilesystemAdapter $private): int
    {
        $missing = 0;
        foreach (self::FILE_COLUMNS as $table => $column) {
            $paths = DB::table($table)
                ->whereNotNull($column)
                // soubor smazaného dokumentu smazal už DocumentController::destroy
                ->when($table === 'documents', fn ($query) => $query->whereNull('deleted_at'))
                ->pluck($column);

            foreach ($paths as $path) {
                if (! $private->exists($path)) {
                    $missing++;
                    $this->warn("Missing file: {$table}.{$column} = {$path}");
                }
            }
        }

        return $missing;
    }
}

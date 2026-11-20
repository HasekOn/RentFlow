<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Support\PrivateFile;

/**
 * Smazání dokumentu i se souborem (soubor až po smazání záznamu). Autorizaci dělá volající.
 */
final class DeleteDocument
{
    public function handle(Document $document): void
    {
        $document->delete();
        PrivateFile::delete($document->file_path);
    }
}

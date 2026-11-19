<?php

namespace App\Actions\Documents;

use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\Property;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Http\UploadedFile;

/**
 * Dokument nemovitosti na privátní disk. Autorizaci a validaci souboru dělá volající.
 */
final class StoreDocument
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (document_type, name, visibility)
     */
    public function handle(Property $property, User $user, UploadedFile $file, array $data): Document
    {
        return PrivateFile::storeWith($file, 'documents/'.$property->id, fn (string $path) => Document::query()->create([
            'property_id' => $property->id,
            'document_type' => $data['document_type'],
            'name' => $data['name'],
            'file_path' => $path,
            'visibility' => $data['visibility'] ?? DocumentVisibility::LandlordOnly->value,
            'uploaded_by' => $user->id,
        ]));
    }
}

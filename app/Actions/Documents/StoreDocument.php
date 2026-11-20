<?php

namespace App\Actions\Documents;

use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Dokument nemovitosti na privátní disk, volitelně navázaný na smlouvu téže nemovitosti (S6). Autorizaci
 * a validaci souboru dělá volající.
 */
final class StoreDocument
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (document_type, name, visibility, lease_id)
     */
    public function handle(Property $property, User $user, UploadedFile $file, array $data): Document
    {
        $leaseId = $data['lease_id'] ?? null;
        if ($leaseId !== null && ! Lease::withTrashed()->where('property_id', $property->id)->whereKey($leaseId)->exists()) {
            throw ValidationException::withMessages([
                'lease_id' => __('The lease does not belong to this property.'),
            ]);
        }

        return PrivateFile::storeWith($file, 'documents/'.$property->id, fn (string $path) => Document::query()->create([
            'property_id' => $property->id,
            'lease_id' => $leaseId,
            'document_type' => $data['document_type'],
            'name' => $data['name'],
            'file_path' => $path,
            'visibility' => $data['visibility'] ?? DocumentVisibility::LandlordOnly->value,
            'uploaded_by' => $user->id,
        ]));
    }
}

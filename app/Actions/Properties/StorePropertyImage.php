<?php

namespace App\Actions\Properties;

use App\Enums\PropertyImageType;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Http\UploadedFile;

/**
 * Fotka nemovitosti na privátní disk. Autorizaci a validaci souboru dělá volající.
 */
final class StorePropertyImage
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (type, description, sort_order)
     */
    public function handle(Property $property, User $user, UploadedFile $file, array $data): PropertyImage
    {
        return PrivateFile::storeWith($file, 'properties/'.$property->id, fn (string $path) => PropertyImage::query()->create([
            'property_id' => $property->id,
            'image_path' => $path,
            'type' => $data['type'] ?? PropertyImageType::Marketing->value,
            'uploaded_by' => $user->id,
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]));
    }
}

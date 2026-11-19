<?php

namespace App\Actions\Properties;

use App\Models\PropertyImage;

/**
 * Úprava popisu, typu a pořadí fotky (soubor se nemění).
 */
final class UpdatePropertyImage
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(PropertyImage $image, array $data): PropertyImage
    {
        $image->update($data);

        return $image;
    }
}

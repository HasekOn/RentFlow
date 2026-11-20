<?php

namespace App\Actions\Properties;

use App\Models\PropertyImage;
use App\Support\PrivateFile;

/**
 * Smazání fotky i se souborem (soubor až po smazání záznamu, ať při chybě DB nezůstane záznam bez souboru).
 */
final class DeletePropertyImage
{
    public function handle(PropertyImage $image): void
    {
        $image->delete();
        PrivateFile::delete($image->image_path);
    }
}

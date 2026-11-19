<?php

namespace App\Actions\Notices;

use App\Models\Notice;

/**
 * Smazání oznámení. Autorizaci dělá volající.
 */
final class DeleteNotice
{
    public function handle(Notice $notice): void
    {
        $notice->delete();
    }
}

<?php

namespace App\Actions\Users;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Označení notifikace jako přečtené. Že je vlastní, ověří volající (hledá ji v notifikacích přihlášeného).
 */
final class MarkNotificationRead
{
    public function handle(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }
}

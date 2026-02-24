<?php

namespace App\Listeners;

use App\Events\LeaseCreated;
use App\Notifications\TenantInvitationNotification;

class SendTenantInvitation
{
    public function handle(LeaseCreated $event): void
    {
        $lease = $event->lease;
        
        $lease->load('tenant');

        if ($lease->tenant) {
            $lease->tenant->notify(new TenantInvitationNotification($lease));
        }
    }
}

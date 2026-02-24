<?php

namespace App\Providers;

use App\Events\LeaseCreated;
use App\Events\PaymentMarkedPaid;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Listeners\RecalculateTrustScore;
use App\Listeners\SendPaymentMarkedNotification;
use App\Listeners\SendTenantInvitation;
use App\Listeners\SendTicketCreatedNotification;
use App\Listeners\SendTicketResolvedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        LeaseCreated::class => [
            SendTenantInvitation::class,
        ],
        TicketCreated::class => [
            SendTicketCreatedNotification::class,
        ],
        TicketResolved::class => [
            SendTicketResolvedNotification::class,
        ],
        PaymentMarkedPaid::class => [
            RecalculateTrustScore::class,
            SendPaymentMarkedNotification::class,
        ],
    ];
}

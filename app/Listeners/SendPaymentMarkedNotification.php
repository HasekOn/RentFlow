<?php

namespace App\Listeners;

use App\Events\PaymentMarkedPaid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentMarkedNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentMarkedPaid $event): void
    {
        //
    }
}

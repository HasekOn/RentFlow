<?php

namespace App\Listeners;

use App\Events\PaymentMarkedPaid;

class SendPaymentMarkedNotification
{
    public function handle(PaymentMarkedPaid $event): void
    {
        // Future: send payment confirmation to tenant
        // For now this listener is a placeholder showing
        // how multiple listeners can react to one event
    }
}

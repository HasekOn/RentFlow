<?php

namespace App\Listeners;

use App\Events\PaymentMarkedPaid;

class RecalculateTrustScore
{
    public function handle(PaymentMarkedPaid $event): void
    {
        $payment = $event->payment;
        
        $payment->load('lease.tenant');

        $tenant = $payment->lease?->tenant;

        $tenant?->recalculateTrustScore();
    }
}

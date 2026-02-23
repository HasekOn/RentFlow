<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Notifications\PaymentOverdueNotification;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CheckOverduePayments extends Command
{
    protected $signature = 'rentflow:check-overdue-payments';
    protected $description = 'Notify tenants about overdue payments and update their trust scores';

    public function handle(): int
    {
        // Find payments that are 5+ days overdue and still unpaid
        $payments = Payment::with(['lease.tenant', 'lease.property'])
            ->where('status', 'unpaid')
            ->where('due_date', '<', now()->subDays(5))
            ->get();

        $count = 0;

        foreach ($payments as $payment) {
            // Mark as overdue
            $payment->update(['status' => 'overdue']);

            // Notify tenant
            $tenant = $payment->lease->tenant;
            if ($tenant) {
                $tenant->notify(new PaymentOverdueNotification($payment));

                // Recalculate trust score (overdue hurts score)
                $tenant->recalculateTrustScore();
            }

            $count++;
        }

        $this->info("Checked overdue payments. Processed $count overdue payments.");

        return CommandAlias::SUCCESS;
    }
}

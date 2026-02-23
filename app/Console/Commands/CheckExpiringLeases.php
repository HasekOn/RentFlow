<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Notifications\LeaseExpiringNotification;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CheckExpiringLeases extends Command
{
    protected $signature = 'rentflow:check-expiring-leases';
    protected $description = 'Notify tenants and landlords about leases expiring within 30 days';

    public function handle(): int
    {
        $leases = Lease::with(['property.landlord', 'tenant'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->get();

        $count = 0;

        foreach ($leases as $lease) {
            // Notify tenant
            if ($lease->tenant) {
                $lease->tenant->notify(new LeaseExpiringNotification($lease));
            }

            // Notify landlord
            if ($lease->property && $lease->property->landlord) {
                $lease->property->landlord->notify(new LeaseExpiringNotification($lease));
            }

            $count++;
        }

        $this->info("Checked expiring leases. Notifications sent for $count leases.");

        return CommandAlias::SUCCESS;
    }
}

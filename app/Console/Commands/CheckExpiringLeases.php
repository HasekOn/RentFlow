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
        // 1. Notify about leases expiring within 30 days
        $expiring = Lease::with(['property.landlord', 'tenant'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->get();

        $notified = 0;
        foreach ($expiring as $lease) {
            if ($lease->tenant) {
                $lease->tenant->notify(new LeaseExpiringNotification($lease));
            }
            if ($lease->property?->landlord) {
                $lease->property->landlord->notify(new LeaseExpiringNotification($lease));
            }
            $notified++;
        }

        $this->info("Expiring notifications sent: {$notified}");

        // 2. Auto-expire leases past end_date
        $expired = Lease::query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString())
            ->get();

        foreach ($expired as $lease) {
            $lease->update(['status' => 'ended']);
            $this->info("Lease #{$lease->id} auto-expired");
        }

        $this->info("Auto-expired leases: {$expired->count()}");

        // 3. Auto-set properties without active lease to 'available'
        $occupiedProperties = \App\Models\Property::query()
            ->where('status', 'occupied')
            ->get();

        $freed = 0;

        foreach ($occupiedProperties as $property) {
            $hasActiveLease = $property->leases()
                ->where('status', 'active')
                ->exists();

            if (! $hasActiveLease) {
                $property->update(['status' => 'available']);
                $freed++;
                $this->info("Property #{$property->id} ({$property->address}) → available");
            }
        }

        $this->info("Properties freed: {$freed}");

        return CommandAlias::SUCCESS;
    }
}

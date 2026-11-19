<?php

namespace App\Console\Commands;

use App\Actions\Leases\ExpireLeases;
use App\Actions\Leases\NotifyExpiringLeases;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CheckExpiringLeases extends Command
{
    protected $signature = 'rentflow:check-expiring-leases';

    protected $description = 'Notify about leases ending in 30 and 7 days, expire ended leases and free their properties';

    public function handle(NotifyExpiringLeases $notify, ExpireLeases $expire): int
    {
        $notified = $notify->handle(today());
        $this->info("Expiring notifications sent: {$notified}");

        $result = $expire->handle(today());
        $this->info("Auto-expired leases: {$result['expired']}");
        $this->info("Properties freed: {$result['freed']}");

        return CommandAlias::SUCCESS;
    }
}

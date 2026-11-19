<?php

namespace App\Console\Commands;

use App\Actions\Payments\MarkOverduePayments;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CheckOverduePayments extends Command
{
    protected $signature = 'rentflow:check-overdue-payments';

    protected $description = 'Notify tenants about overdue payments and update their trust scores';

    public function handle(MarkOverduePayments $markOverdue): int
    {
        $count = $markOverdue->handle(today());

        $this->info("Checked overdue payments. Processed $count overdue payments.");

        return CommandAlias::SUCCESS;
    }
}

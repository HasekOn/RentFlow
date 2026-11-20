<?php

namespace App\Console\Commands;

use App\Actions\Payments\GenerateMonthlyPayments as GenerateMonthlyPaymentsAction;
use Illuminate\Console\Command;

class GenerateMonthlyPayments extends Command
{
    protected $signature = 'rentflow:generate-payments';

    protected $description = 'Generate monthly rent and utility payments for active leases';

    public function handle(GenerateMonthlyPaymentsAction $generate): int
    {
        $created = $generate->handle(null, now());

        $this->info("Total payments generated: {$created}");

        return self::SUCCESS;
    }
}

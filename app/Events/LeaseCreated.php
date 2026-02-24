<?php

namespace App\Events;

use App\Models\Lease;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaseCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Lease $lease,
    ) {}
}

<?php

namespace App\Console\Commands;

use App\Services\PriorityClaimService;
use Illuminate\Console\Command;

class ExpirePriorityClaims extends Command
{
    protected $signature = 'priority:expire';

    protected $description = 'Mark approved priority claims past their valid_until date as expired';

    public function handle(PriorityClaimService $service): int
    {
        $this->info("Expired {$service->expireDue()} claim(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Platform\PaystackPlatformPlanService;
use Illuminate\Console\Command;

class ProvisionPlatformBillingPlans extends Command
{
    protected $signature = 'platform:provision-plans';

    protected $description = 'Idempotently provision the 6 ShootingSports subscription plans on Paystack.';

    public function handle(PaystackPlatformPlanService $service): int
    {
        foreach ($service->provisionPlans() as $key => $planCode) {
            $this->line("  {$key}: {$planCode}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}

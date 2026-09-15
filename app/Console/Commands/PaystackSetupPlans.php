<?php

namespace App\Console\Commands;

use App\Services\Paystack\PaystackClient;
use Illuminate\Console\Command;

/**
 * One-shot setup: create (or verify) the two Pro plans on the currently
 * configured Paystack account and print the PLN_ codes for the operator
 * to paste into the server .env.
 *
 * Idempotent — reruns are safe. The command looks the plans up by name
 * before creating so re-running won't spawn duplicates in the Paystack
 * dashboard. Values come from config/plans.php so the display and the
 * charged amount can never disagree.
 *
 * Usage on the server:
 *   docker exec shootingsports-app php artisan paystack:setup-plans
 *   # copy the two PLN_ codes into .env under PAYSTACK_PLAN_ANNUAL /
 *   # PAYSTACK_PLAN_MONTHLY, then:
 *   docker exec shootingsports-app php artisan config:cache
 */
class PaystackSetupPlans extends Command
{
    protected $signature = 'paystack:setup-plans';

    protected $description = 'Idempotently create the Pro annual + monthly plans on Paystack and print their codes.';

    public function handle(PaystackClient $paystack): int
    {
        if (blank(config('services.paystack.secret_key'))) {
            $this->error('PAYSTACK_SECRET_KEY is not set. Add it to .env and rerun.');

            return self::FAILURE;
        }

        $annualAmount = (int) config('plans.pricing.annual.amount_cents');
        $monthlyAmount = (int) config('plans.pricing.monthly.amount_cents');

        if ($annualAmount <= 0 || $monthlyAmount <= 0) {
            $this->error('config/plans.php pricing amounts must be > 0 cents.');

            return self::FAILURE;
        }

        $this->line('Currency: '.config('services.paystack.currency', 'ZAR'));
        $this->line("Annual  : {$annualAmount} cents (".($annualAmount / 100).' per year)');
        $this->line("Monthly : {$monthlyAmount} cents (".($monthlyAmount / 100).' per month)');
        $this->newLine();

        try {
            $annual = $paystack->createPlan('Shooting Sports Pro (Annual)', 'annually', $annualAmount);
            $monthly = $paystack->createPlan('Shooting Sports Pro (Monthly)', 'monthly', $monthlyAmount);
        } catch (\Throwable $e) {
            $this->error('Paystack call failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(($annual['created'] ? 'Created' : 'Reused').' annual plan  : '.$annual['plan_code']);
        $this->info(($monthly['created'] ? 'Created' : 'Reused').' monthly plan : '.$monthly['plan_code']);
        $this->newLine();

        $this->line('Add these to your server .env:');
        $this->newLine();
        $this->line('PAYSTACK_PLAN_ANNUAL='.$annual['plan_code']);
        $this->line('PAYSTACK_PLAN_MONTHLY='.$monthly['plan_code']);
        $this->newLine();
        $this->line('Then: php artisan config:cache');

        return self::SUCCESS;
    }
}

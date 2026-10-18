<?php

namespace App\Console\Commands;

use App\Models\PlatformPayout;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Writes pending club payouts to stdout as a bank-upload CSV.
 * With --mark-exported the payouts move to `exported` afterwards.
 *
 * Banking details are not yet stored on organisations, so those
 * columns are written as TBD placeholders. The warning goes to stderr
 * so it never pollutes a redirected CSV.
 */
#[AsCommand(name: 'platform:batch-club-payouts')]
class BatchClubPayouts extends Command
{
    protected $signature = 'platform:batch-club-payouts
        {--mark-exported : Mark the exported payouts as exported}';

    protected $description = 'Export pending club payouts as a bank-upload CSV.';

    public function handle(): int
    {
        $payouts = PlatformPayout::query()
            ->where('status', 'pending')
            ->with('organisation', 'payables')
            ->get();

        $message = 'TBD: add settlement_account_number / settlement_bank_code / settlement_account_name to organisations table';
        $output = $this->output->getOutput();

        if ($output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->writeln($message);
        } else {
            $this->warn($message);
        }

        $csv = fopen('php://temp', 'w+');

        fputcsv($csv, ['club', 'account_name', 'account_number', 'bank_code', 'amount_cents', 'amount_rands', 'payout_id'], escape: '');

        foreach ($payouts as $payout) {
            fputcsv($csv, [
                $payout->organisation?->name ?? 'TBD',
                'TBD',
                'TBD',
                'TBD',
                $payout->net_cents,
                number_format($payout->net_cents / 100, 2, '.', ''),
                $payout->id,
            ], escape: '');
        }

        rewind($csv);
        $this->output->write((string) stream_get_contents($csv));
        fclose($csv);

        if ($this->option('mark-exported')) {
            foreach ($payouts as $payout) {
                $payout->update(['status' => 'exported', 'exported_at' => now()]);
            }
        }

        return self::SUCCESS;
    }
}

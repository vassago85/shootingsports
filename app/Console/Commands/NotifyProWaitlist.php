<?php

namespace App\Console\Commands;

use App\Enums\EmailCategory;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Mail\ProSalesOpen;
use App\Models\Enquiry;
use App\Support\EmailPreferences;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails people who joined the Pro waitlist once sales are open.
 * One message per address. Linked accounts are skipped when they
 * have turned product updates off. Safe to run again: a stamped
 * enquiry is not mailed a second time.
 */
class NotifyProWaitlist extends Command
{
    protected $signature = 'pro:notify-waitlist {--dry : List recipients without sending}';

    protected $description = 'Email Pro waitlist enquiries that sales are open, honouring email preferences.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $sent = 0;
        $skipped = 0;
        /** @var array<string, true> $seen */
        $seen = [];

        Enquiry::query()
            ->where('type', EnquiryType::ProWaitlist)
            ->where('status', '!=', EnquiryStatus::PendingConfirmation)
            ->orderBy('id')
            ->each(function (Enquiry $enquiry) use ($dry, &$sent, &$skipped, &$seen): void {
                $email = strtolower(trim((string) $enquiry->email));
                $context = $enquiry->context ?? [];

                if ($email === '' || isset($context['sales_opened_notified_at']) || isset($seen[$email])) {
                    return;
                }

                $user = $enquiry->user;

                if ($user !== null && ! EmailPreferences::canSend($user, EmailCategory::ProductUpdates)) {
                    $skipped++;
                    $seen[$email] = true;

                    return;
                }

                $seen[$email] = true;

                if ($dry) {
                    $this->line($email);
                    $sent++;

                    return;
                }

                Mail::to($email)->send(new ProSalesOpen(
                    recipientName: $enquiry->name !== '' ? $enquiry->name : 'there',
                    user: $user,
                ));

                Enquiry::query()
                    ->where('type', EnquiryType::ProWaitlist)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->each(function (Enquiry $row): void {
                        $rowContext = $row->context ?? [];
                        $rowContext['sales_opened_notified_at'] = now()->toIso8601String();
                        $row->forceFill(['context' => $rowContext])->save();
                    });

                $sent++;
            });

        $this->info(sprintf(
            '%s waitlist %s%s. %s skipped because of email preferences.',
            $sent,
            $sent === 1 ? 'email' : 'emails',
            $dry ? ' (dry run — nothing sent)' : ' queued',
            $skipped,
        ));

        return self::SUCCESS;
    }
}

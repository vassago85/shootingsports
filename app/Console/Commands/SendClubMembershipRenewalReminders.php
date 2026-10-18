<?php

namespace App\Console\Commands;

use App\Enums\ClubMemberStatus;
use App\Mail\ClubMembershipRenewalReminder;
use App\Models\ClubMember;
use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Daily reminder sweep. For each managed club, walks every Active
 * member with an upcoming expiry and queues the renewal mail at the
 * configured reminder thresholds (default [30, 14, 7] days).
 *
 * Idempotency: each threshold has its own `reminder_{n}_sent_at`
 * stamp on `club_members`. The command only sends when the stamp is
 * null, so a second run the same day is a no-op. The ClubMember
 * model's `renew()` helper clears the stamps when the member is
 * renewed, so the next cycle's reminders go out again.
 *
 * Reminder thresholds are a per-club list of day-counts, matched by
 * `floor(hoursUntilExpiry/24) <= threshold`. The sweep walks the
 * configured list from largest to smallest and sends at most one
 * reminder per run per member (the smallest threshold that both
 * fires AND is still unsent — i.e. the most urgent).
 */
class SendClubMembershipRenewalReminders extends Command
{
    protected $signature = 'club-members:send-renewal-reminders {--dry : List candidates without sending}';

    protected $description = 'Queue the T-30 / T-14 / T-7 (per-club configurable) renewal reminders. Idempotent.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $today = Carbon::today();
        $sent = 0;

        $organisationIds = Organisation::query()
            ->where('membership_enabled', true)
            ->pluck('id');

        foreach ($organisationIds as $organisationId) {
            $organisation = Organisation::query()->find($organisationId);
            if ($organisation === null) {
                continue;
            }

            $thresholds = $organisation->renewalReminderDaysOrDefault();
            $maxThreshold = max($thresholds);

            // Materialise with get() rather than cursor(): we write
            // back to the row inside the loop, and some drivers
            // (SQLite in particular) re-yield mutated rows when a
            // lazy cursor is iterated. get() takes a snapshot and
            // avoids the duplicate send.
            $candidates = ClubMember::query()
                ->where('organisation_id', $organisation->id)
                ->where('status', ClubMemberStatus::Active)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today, $today->copy()->addDays($maxThreshold)])
                ->get();

            foreach ($candidates as $member) {
                $daysUntilExpiry = (int) $today->diffInDays($member->expires_at, false);

                if ($daysUntilExpiry < 0) {
                    continue;
                }

                $threshold = $this->firstMatchingThreshold($member, $thresholds, $daysUntilExpiry);

                if ($threshold === null) {
                    continue;
                }

                if (! filled($member->email)) {
                    continue;
                }

                if (! $dry) {
                    Mail::to($member->email)->queue(new ClubMembershipRenewalReminder($member, $threshold));
                    $member->forceFill([
                        $this->stampColumn($threshold) => now(),
                    ])->save();
                }

                $sent++;
            }
        }

        $this->info(sprintf(
            '%d reminder%s queued%s.',
            $sent,
            $sent === 1 ? '' : 's',
            $dry ? ' (dry run — nothing sent)' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * The smallest configured threshold that is >= the member's actual
     * days-until-expiry, provided its stamp is unset. Returns null in
     * every other case:
     *   - the member is not inside any reminder window yet;
     *   - the current window's reminder has already been sent;
     *   - a reminder was previously sent at a larger threshold — but
     *     once the window shrinks the smaller threshold's stamp is
     *     still unset, so that reminder still fires on its first day.
     *
     * The key property this preserves is "one reminder per threshold
     * per renewal cycle": repeat runs the same day are no-ops, and
     * `ClubMember::renew()` clears every stamp so the next cycle's
     * reminders can go out again.
     *
     * @param  list<int>  $thresholds
     */
    private function firstMatchingThreshold(ClubMember $member, array $thresholds, int $daysUntilExpiry): ?int
    {
        $ascending = $thresholds;
        sort($ascending);

        $smallest = null;

        foreach ($ascending as $threshold) {
            if ($daysUntilExpiry <= $threshold) {
                $smallest = $threshold;

                break;
            }
        }

        if ($smallest === null) {
            return null;
        }

        if ($member->{$this->stampColumn($smallest)} !== null) {
            return null;
        }

        return $smallest;
    }

    private function stampColumn(int $threshold): string
    {
        return match (true) {
            $threshold <= 7 => 'reminder_7_sent_at',
            $threshold <= 14 => 'reminder_14_sent_at',
            default => 'reminder_30_sent_at',
        };
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\ClubMemberStatus;
use App\Models\ClubMember;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily sweep that flips Active members whose expiry has passed to
 * Lapsed through the model's transition machinery (so a row lands in
 * club_member_status_changes with reason "expired").
 *
 * Idempotent: once a row is Lapsed it is no longer selected by the
 * `active + expired` filter, so re-running the command the same day
 * does nothing.
 *
 * Wired up in routes/console.php as a daily 07:30 SAST task.
 */
class LapseExpiredClubMembers extends Command
{
    protected $signature = 'club-members:lapse {--dry : Count candidates without updating}';

    protected $description = 'Mark active club members whose expiry has passed as Lapsed, writing an audit entry.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $today = Carbon::today();

        $query = ClubMember::query()
            ->where('status', ClubMemberStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $today);

        $total = 0;

        $query->cursor()->each(function (ClubMember $member) use (&$total, $dry): void {
            if (! $dry) {
                $member->transitionTo(ClubMemberStatus::Lapsed, actor: null, reason: 'expired');
            }

            $total++;
        });

        $this->info(sprintf(
            '%d member%s lapsed%s.',
            $total,
            $total === 1 ? '' : 's',
            $dry ? ' (dry run — nothing updated)' : '',
        ));

        return self::SUCCESS;
    }
}

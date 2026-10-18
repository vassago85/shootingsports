<?php

namespace App\Console\Commands;

use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\OrganisationUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Phase B — idempotent backfill. Runs on every deploy without
 * duplicating rows:
 *
 *   1. Promote existing is_staff=true users to UserRole::Staff.
 *   2. For every approved match director, insert one
 *      organisation_user row (role=match_director, status=approved)
 *      per organisation that is host_organisation_id on an event
 *      they created, if no row already exists.
 *
 * Nothing is deleted; nothing is updated except roles / timestamps
 * that are strictly additive. Safe to run repeatedly.
 */
class PhaseBBackfillMemberships extends Command
{
    protected $signature = 'app:phase-b-backfill-memberships
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Phase B — populate users.role and organisation_user memberships for existing match directors.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Dry run — no writes will happen.' : 'Writing changes.');

        $staffPromoted = $this->promoteStaffRoles($dryRun);
        $this->line("  users promoted to role=staff: {$staffPromoted}");

        $pivotsInserted = $this->backfillDirectorMemberships($dryRun);
        $this->line("  organisation_user rows inserted: {$pivotsInserted}");

        return self::SUCCESS;
    }

    /**
     * Any user with the legacy is_staff flag gets role=staff unless
     * they already have a role set (admins keep admin).
     */
    protected function promoteStaffRoles(bool $dryRun): int
    {
        $query = User::query()
            ->where('is_staff', true)
            ->where(function ($q): void {
                $q->whereNull('role')->orWhere('role', UserRole::User->value);
            });

        $count = $query->count();

        if (! $dryRun && $count > 0) {
            $query->update(['role' => UserRole::Staff->value]);
        }

        return $count;
    }

    /**
     * One organisation_user row per (director, host-org) distinct
     * pair, where director = `created_by` on the event. Only touches
     * approved match directors (is_match_director=true) so pending
     * applicants don't silently gain org access.
     */
    protected function backfillDirectorMemberships(bool $dryRun): int
    {
        $directors = User::query()
            ->where('is_match_director', true)
            ->pluck('id');

        if ($directors->isEmpty()) {
            return 0;
        }

        /** @var Collection<int, object{user_id:int, host_organisation_id:int}> $pairs */
        $pairs = Event::query()
            ->whereIn('created_by', $directors)
            ->whereNotNull('host_organisation_id')
            ->select(['created_by as user_id', 'host_organisation_id'])
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            return 0;
        }

        $existing = OrganisationUser::query()
            ->whereIn('user_id', $pairs->pluck('user_id'))
            ->whereIn('organisation_id', $pairs->pluck('host_organisation_id'))
            ->get(['user_id', 'organisation_id'])
            ->map(fn (OrganisationUser $row): string => $row->user_id.':'.$row->organisation_id)
            ->flip();

        $inserted = 0;
        $now = now();

        foreach ($pairs as $pair) {
            $key = $pair->user_id.':'.$pair->host_organisation_id;
            if ($existing->has($key)) {
                continue;
            }

            if (! $dryRun) {
                OrganisationUser::query()->create([
                    'user_id' => $pair->user_id,
                    'organisation_id' => $pair->host_organisation_id,
                    'role' => OrganisationUserRole::MatchDirector->value,
                    'status' => MembershipStatus::Approved->value,
                    'approved_at' => $now,
                    'granted_at' => $now,
                ]);
            }

            $inserted++;
        }

        return $inserted;
    }
}

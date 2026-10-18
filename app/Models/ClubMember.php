<?php

namespace App\Models;

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ClubMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A member of a club's register. Lives alongside — not inside —
 * {@see User}: imported paper members have no account yet, and ex-member
 * records survive even when an account is deleted.
 */
#[Fillable([
    'organisation_id', 'user_id',
    'member_number', 'first_name', 'last_name', 'email', 'phone',
    'date_of_birth', 'category', 'status',
    'joined_at', 'expires_at',
    'emergency_contact_name', 'emergency_contact_phone',
    'popia_consented_at', 'notes',
    'reminder_30_sent_at', 'reminder_14_sent_at', 'reminder_7_sent_at',
    'custom_attributes',
])]
class ClubMember extends Model
{
    /** @use HasFactory<ClubMemberFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => ClubMemberCategory::class,
            'status' => ClubMemberStatus::class,
            'date_of_birth' => 'immutable_date',
            'joined_at' => 'immutable_date',
            'expires_at' => 'immutable_date',
            'popia_consented_at' => 'immutable_datetime',
            'reminder_30_sent_at' => 'immutable_datetime',
            'reminder_14_sent_at' => 'immutable_datetime',
            'reminder_7_sent_at' => 'immutable_datetime',
            'custom_attributes' => 'array',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ClubMemberStatusChange::class)->orderBy('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MembershipInvoice::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Status as reported to users: an Active record whose expiry has
     * passed shows as Lapsed. The stored column keeps saying Active so
     * reinstating is a no-op write — Phase 2 adds the scheduled sweep.
     */
    public function effectiveStatus(): ClubMemberStatus
    {
        if ($this->status === ClubMemberStatus::Active && $this->isExpired()) {
            return ClubMemberStatus::Lapsed;
        }

        return $this->status;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(Carbon::today());
    }

    /**
     * Record a transition and persist it. Writing goes through this
     * single method so every change — Desk action, Livewire form, CSV
     * import, listener — ends up in the history table.
     */
    public function transitionTo(ClubMemberStatus $to, ?User $actor = null, ?string $reason = null): void
    {
        $from = $this->status;

        $this->forceFill(['status' => $to])->save();

        $this->statusChanges()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'changed_by' => $actor?->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Renew this member to a new expiry. Sets status to Active, stamps
     * the status-change audit log and clears the reminder stamps so
     * the next cycle's reminders can fire.
     */
    public function renew(CarbonImmutable $expiresAt, ?User $actor = null, ?string $reason = null): void
    {
        $from = $this->status;

        $this->forceFill([
            'status' => ClubMemberStatus::Active,
            'expires_at' => $expiresAt->toDateString(),
            'joined_at' => $this->joined_at ?? Carbon::today()->toDateString(),
            'reminder_30_sent_at' => null,
            'reminder_14_sent_at' => null,
            'reminder_7_sent_at' => null,
        ])->save();

        $this->statusChanges()->create([
            'from_status' => $from->value,
            'to_status' => ClubMemberStatus::Active->value,
            'changed_by' => $actor?->id,
            'reason' => $reason ?? 'Renewed',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ClubMemberStatus::Active)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', Carbon::today());
            });
    }

    public function scopeLapsed(Builder $query): Builder
    {
        return $query->where('status', ClubMemberStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::today());
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->where('status', ClubMemberStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [Carbon::today(), Carbon::today()->addDays($days)]);
    }
}

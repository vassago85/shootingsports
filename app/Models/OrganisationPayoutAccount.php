<?php

namespace App\Models;

use App\Enums\PayoutAccountType;
use App\Enums\PayoutDocumentType;
use App\Enums\PayoutStatus;
use Database\Factories\OrganisationPayoutAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'organisation_id', 'status', 'paystack_subaccount_code',
    'account_holder_name', 'bank_code', 'bank_name', 'bank_enabled_for_verification',
    'account_number', 'account_last_four', 'account_type',
    'document_type', 'document_number',
    'validation_verified', 'validation_message', 'validation_checks', 'validation_checked_at',
    'terms_accepted_at', 'terms_version', 'submitted_at',
    'approved_by', 'approved_at',
])]
#[Hidden(['account_number', 'document_number'])]
class OrganisationPayoutAccount extends Model
{
    /** @use HasFactory<OrganisationPayoutAccountFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'bank_enabled_for_verification' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (OrganisationPayoutAccount $account): void {
            if ($account->isDirty('account_number') && filled($account->account_number)) {
                $account->account_last_four = self::lastFour((string) $account->account_number);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'bank_enabled_for_verification' => 'boolean',
            'account_number' => 'encrypted',
            'account_type' => PayoutAccountType::class,
            'document_type' => PayoutDocumentType::class,
            'document_number' => 'encrypted',
            'validation_verified' => 'boolean',
            'validation_checks' => 'array',
            'validation_checked_at' => 'immutable_datetime',
            'terms_accepted_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(OrganisationPayoutAccountChange::class);
    }

    public function acceptsOnlinePayments(): bool
    {
        return $this->status === PayoutStatus::Active
            && filled($this->paystack_subaccount_code);
    }

    public function maskedAccountNumber(): string
    {
        if (blank($this->account_last_four)) {
            return '';
        }

        return '••••'.$this->account_last_four;
    }

    public function maskedLabel(): string
    {
        $bank = $this->bank_name !== '' ? $this->bank_name : 'Bank';

        if (blank($this->account_last_four)) {
            return $bank;
        }

        return $bank.' '.$this->maskedAccountNumber();
    }

    public static function lastFour(string $accountNumber): string
    {
        return Str::substr(trim($accountNumber), -4);
    }

    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::PendingApproval);
    }

    public function scopeStalePendingApproval(Builder $query): Builder
    {
        $hours = max(0, (int) config('paystack.approval_stale_hours', 48));

        return $query
            ->where('status', PayoutStatus::PendingApproval)
            ->where('submitted_at', '<=', now()->subHours($hours));
    }

    public function recordChange(
        ?User $user,
        ?string $oldLastFour,
        ?string $newLastFour,
        ?string $oldBankName,
        ?string $newBankName,
        ?string $oldAccountHolder,
        ?string $newAccountHolder,
        ?PayoutStatus $oldStatus,
        ?PayoutStatus $newStatus,
    ): OrganisationPayoutAccountChange {
        return $this->changes()->create([
            'user_id' => $user?->id,
            'old_last_four' => $oldLastFour,
            'new_last_four' => $newLastFour,
            'old_bank_name' => $oldBankName,
            'new_bank_name' => $newBankName,
            'old_account_holder' => $oldAccountHolder,
            'new_account_holder' => $newAccountHolder,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
    }
}

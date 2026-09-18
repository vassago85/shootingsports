<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[Fillable([
    'type', 'user_id', 'name', 'email', 'phone', 'subject', 'body', 'context',
    'about_type', 'about_id', 'status', 'ip_address', 'user_agent', 'read_at',
    'confirmation_token', 'confirmation_sent_at', 'confirmed_at',
])]
class Enquiry extends Model
{
    public const int CONFIRMATION_TTL_HOURS = 48;

    protected function casts(): array
    {
        return [
            'type' => EnquiryType::class,
            'status' => EnquiryStatus::class,
            'read_at' => 'immutable_datetime',
            'confirmation_sent_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'context' => 'array',
        ];
    }

    public function about(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markRead(): void
    {
        if ($this->status === EnquiryStatus::New) {
            $this->forceFill([
                'status' => EnquiryStatus::Read,
                'read_at' => now(),
            ])->save();
        }
    }

    public function issueConfirmationToken(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'confirmation_token' => $token,
            'confirmation_sent_at' => now(),
            'confirmed_at' => null,
            'status' => EnquiryStatus::PendingConfirmation,
        ])->save();

        return $token;
    }

    public function confirmationIsExpired(): bool
    {
        if ($this->confirmation_sent_at === null) {
            return true;
        }

        return $this->confirmation_sent_at
            ->addHours(self::CONFIRMATION_TTL_HOURS)
            ->isPast();
    }

    public function markConfirmed(): void
    {
        $this->forceFill([
            'status' => EnquiryStatus::New,
            'confirmed_at' => now(),
            'confirmation_token' => null,
        ])->save();
    }
}

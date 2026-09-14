<?php

namespace App\Models\Concerns;

use App\Enums\VerificationState;
use Illuminate\Support\Str;

trait HasVerification
{
    public const AGEING_AFTER_DAYS = 180;

    public function markVerified(): void
    {
        $this->forceFill([
            'verification_state' => VerificationState::Verified,
            'last_verified_at' => now(),
            'verification_token' => null,
        ])->save();
    }

    public function markAgeing(): void
    {
        $this->forceFill([
            'verification_state' => VerificationState::Ageing,
        ])->save();
    }

    public function markUnconfirmed(): void
    {
        $this->forceFill([
            'verification_state' => VerificationState::Unconfirmed,
            'verification_token' => $this->verification_token ?: (string) Str::uuid(),
        ])->save();
    }

    public function archiveVerification(): void
    {
        $this->forceFill([
            'verification_state' => VerificationState::Archived,
        ])->save();
    }

    public function isStale(): bool
    {
        if ($this->last_verified_at === null) {
            return true;
        }

        return $this->last_verified_at->lt(now()->subDays(self::AGEING_AFTER_DAYS));
    }

    public function refreshVerificationState(): void
    {
        if ($this->verification_state === VerificationState::Archived) {
            return;
        }

        if ($this->last_verified_at === null) {
            $this->markUnconfirmed();

            return;
        }

        if ($this->isStale()) {
            $this->markAgeing();

            return;
        }

        $this->forceFill([
            'verification_state' => VerificationState::Verified,
        ])->save();
    }
}

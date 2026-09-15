<?php

namespace App\Exceptions;

use App\Models\User;

/**
 * Thrown when a create/attach would cross a freemium cap. The controller
 * or Livewire component catches this to open the UpgradePrompt with the
 * matching trigger. Never surfaced as a validation toast — the whole
 * point is to invite the upgrade conversation.
 */
class PlanLimitExceeded extends \RuntimeException
{
    public function __construct(
        public readonly User $user,
        public readonly string $limitKey,
        public readonly string $trigger,
        public readonly int $current,
    ) {
        parent::__construct(
            sprintf(
                'Plan limit exceeded for user [%d] on [%s]: %d used, cap %s.',
                $user->getKey(),
                $limitKey,
                $current,
                $user->limit($limitKey) === null ? 'unlimited' : (string) $user->limit($limitKey),
            ),
        );
    }
}

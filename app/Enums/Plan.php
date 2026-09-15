<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

/**
 * Shooter subscription tier. Free is the permanent default; Pro is the
 * paid tier introduced in the freemium foundation. Never used to gate
 * access to public data — only personalisation volume + convenience
 * features (see config/plans.php).
 */
enum Plan: string implements HasLabel
{
    use HasLabelFromValue;

    case Free = 'free';
    case Pro = 'pro';
}

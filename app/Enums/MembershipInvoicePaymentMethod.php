<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

/**
 * How a membership invoice was paid. Mirrors the "how did the money
 * get in?" question secretaries ask when reconciling the club bank
 * statement, plus the two internal methods (Paystack online, Waive).
 */
enum MembershipInvoicePaymentMethod: string implements HasLabel
{
    use HasLabelFromValue;

    case Eft = 'eft';
    case Cash = 'cash';
    case Paystack = 'paystack';
    case Waive = 'waive';
    case Other = 'other';
}

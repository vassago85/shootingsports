<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable-ish record of every webhook delivery from Paystack.
 * Written before we process, updated with processed_at + error on the
 * way out. Primary key is the Paystack event id, so a re-delivery
 * throws a unique-constraint violation which the controller catches
 * as "already seen".
 */
#[Fillable(['id', 'event_type', 'reference', 'payload', 'processed_at', 'error'])]
class PaystackEvent extends Model
{
    protected $table = 'paystack_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use Database\Factories\TeamEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A team entry submitted on the match page and forwarded to the organiser.
 * Members hold SA ID numbers, so the column is encrypted at rest.
 *
 * @property list<array{name: string, id_number: string, phone: string}> $members
 */
#[Fillable([
    'event_id', 'team_name', 'captain_name', 'captain_email', 'captain_phone',
    'members', 'proof_of_payment_path', 'proof_of_payment_name', 'ip_address',
])]
class TeamEntry extends Model
{
    /** @use HasFactory<TeamEntryFactory> */
    use HasFactory;

    public const PROOF_OF_PAYMENT_DISK = 'local';

    protected static function booted(): void
    {
        static::deleted(function (TeamEntry $entry): void {
            if (filled($entry->proof_of_payment_path)) {
                Storage::disk(self::PROOF_OF_PAYMENT_DISK)->delete($entry->proof_of_payment_path);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'members' => 'encrypted:array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

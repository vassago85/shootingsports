<?php

namespace App\Observers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Support\Indexability;
use App\Support\IndexNow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pings IndexNow when a public event, club, federation, range or
 * supplier listing is created, updated or deleted. Drafts and thin
 * listings are skipped. A listing that drops out of the index is
 * still pinged so engines recrawl the URL.
 *
 * Save pings are gated on whether a public-visible field changed —
 * clicking Save in the desk without touching anything, or a
 * `last_verified_at` tick, does not ping. The IndexNow helper
 * additionally debounces by URL so two legitimate edits in quick
 * succession only ping once per cooldown.
 */
class IndexNowObserver
{
    /**
     * Fields whose change signals a public-visible update per model.
     * Anything else (ownership metadata, import timestamps, internal
     * flags) is not worth a ping.
     *
     * @var array<class-string, list<string>>
     */
    private const PUBLIC_FIELDS = [
        Event::class => [
            'slug', 'title', 'status', 'publication', 'starts_at', 'ends_at',
            'venue_id', 'host_organisation_id', 'entry_url', 'entry_fee_cents',
            'member_fee_cents', 'description', 'results_url', 'website_url',
            'level', 'kind', 'deleted_at',
        ],
        Organisation::class => [
            'slug', 'name', 'status', 'type', 'town', 'province',
            'email', 'phone', 'website_url', 'description', 'visitors_welcome',
            'accredited',
        ],
        Venue::class => [
            'slug', 'name', 'status', 'town', 'province', 'address',
            'max_distance_m', 'bay_count', 'access', 'description', 'notes',
            'lat', 'lng',
        ],
        Provider::class => [
            'slug', 'name', 'status', 'category', 'town', 'province',
            'email', 'phone', 'website_url', 'description', 'tagline', 'tier',
        ],
    ];

    public function created(Model $record): void
    {
        // Insert: ping only if the fresh record is indexable — don't
        // tell Bing about a listing that was created already thin.
        if (! $this->isSupported($record)) {
            return;
        }

        if (! Indexability::allows($record)) {
            return;
        }

        $this->tryPing($record);
    }

    public function updated(Model $record): void
    {
        // Update: Eloquent fires `updated` only when a dirty save
        // actually performed the UPDATE, so "re-save without changes"
        // never reaches here. Any update to a public-visible field
        // pings — including the edit that made a listing thin, so the
        // engine drops the URL.
        if (! $this->isSupported($record)) {
            return;
        }

        if (! $this->publicFieldChanged($record)) {
            return;
        }

        $this->tryPing($record);
    }

    public function deleting(Model $record): void
    {
        if ($record instanceof Event && $record->status !== EventStatus::Draft) {
            IndexNow::pingEventSurfaces($record);
        }
    }

    public function deleted(Model $record): void
    {
        if (! $this->isSupported($record)) {
            return;
        }

        $this->tryPing($record);
    }

    public function restored(Model $record): void
    {
        if (! $this->isSupported($record)) {
            return;
        }

        $this->tryPing($record);
    }

    private function tryPing(Event|Organisation|Venue|Provider $record): void
    {
        try {
            IndexNow::dispatchFor($record);

            if ($record instanceof Event) {
                IndexNow::pingEventSurfaces($record);
            }
        } catch (Throwable $e) {
            Log::warning('IndexNow observer failed', [
                'model' => $record::class,
                'id' => $record->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isSupported(Model $record): bool
    {
        return $record instanceof Event
            || $record instanceof Organisation
            || $record instanceof Venue
            || $record instanceof Provider;
    }

    private function publicFieldChanged(Event|Organisation|Venue|Provider $record): bool
    {
        $fields = self::PUBLIC_FIELDS[$record::class] ?? [];

        foreach ($fields as $field) {
            if ($record->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }
}

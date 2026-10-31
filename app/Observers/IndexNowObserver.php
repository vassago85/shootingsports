<?php

namespace App\Observers;

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
 */
class IndexNowObserver
{
    public function saved(Model $record): void
    {
        $this->ping($record, deleted: false);
    }

    public function deleted(Model $record): void
    {
        $this->ping($record, deleted: true);
    }

    public function restored(Model $record): void
    {
        $this->ping($record, deleted: false);
    }

    private function ping(Model $record, bool $deleted): void
    {
        if (! $record instanceof Event
            && ! $record instanceof Organisation
            && ! $record instanceof Venue
            && ! $record instanceof Provider) {
            return;
        }

        try {
            if (! $deleted && ! $this->shouldPing($record)) {
                return;
            }

            IndexNow::dispatchFor($record);
        } catch (Throwable $e) {
            Log::warning('IndexNow observer failed', [
                'model' => $record::class,
                'id' => $record->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shouldPing(Event|Organisation|Venue|Provider $record): bool
    {
        if (Indexability::allows($record)) {
            return true;
        }

        return ! $record->wasRecentlyCreated
            && ($record->wasChanged('status') || $record->wasChanged('slug'));
    }
}

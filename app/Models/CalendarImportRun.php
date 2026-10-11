<?php

namespace App\Models;

use Database\Factories\CalendarImportRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One run of a federation calendar importer, scheduled or started by staff.
 */
#[Fillable(['source', 'created_count', 'updated_count', 'skipped_count', 'error'])]
class CalendarImportRun extends Model
{
    /** @use HasFactory<CalendarImportRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
        ];
    }

    public function failed(): bool
    {
        return filled($this->error);
    }

    public function matchesFound(): int
    {
        return $this->created_count + $this->updated_count + $this->skipped_count;
    }

    public function summary(): string
    {
        $when = $this->created_at->timezone(config('app.timezone'))->format('j M Y H:i');

        if ($this->failed()) {
            return "Failed {$when}: {$this->error}";
        }

        return sprintf(
            '%s: %d created, %d updated, %d staff rows left alone.',
            $when,
            $this->created_count,
            $this->updated_count,
            $this->skipped_count,
        );
    }
}

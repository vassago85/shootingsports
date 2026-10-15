<?php

namespace App\Services\ClubMembers;

/**
 * Outcome of a CSV import run — three counters and a list of row-level
 * errors. Returned by {@see ImportClubMembers::handle()} and rendered
 * in the Desk notification.
 */
class ClubMemberImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var list<array{row: int, error: string}> */
    public array $errors = [];

    public function skip(int $row, string $error): void
    {
        $this->skipped++;
        $this->errors[] = ['row' => $row, 'error' => $error];
    }

    public function summary(): string
    {
        return sprintf(
            '%d created, %d updated, %d skipped.',
            $this->created,
            $this->updated,
            $this->skipped,
        );
    }
}

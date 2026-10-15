<?php

namespace App\Services\ClubMembers;

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Synchronous CSV importer for a club's membership register. Runs in
 * one transaction and matches existing records on member number first,
 * then email, so a re-import of the same file updates members rather
 * than duplicating them.
 */
class ImportClubMembers
{
    /** @var list<string> */
    public const array COLUMNS = [
        'member_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'category',
        'status',
        'joined_at',
        'expires_at',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    public function handle(Organisation $organisation, string $path, ?User $actor = null): ClubMemberImportResult
    {
        $result = new ClubMemberImportResult;

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'Could not open the uploaded file.',
            ]);
        }

        try {
            $header = $this->readHeader($handle);
            $rowNumber = 1;

            DB::transaction(function () use ($organisation, $actor, $handle, $header, &$rowNumber, $result): void {
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;

                    if ($this->isBlank($row)) {
                        continue;
                    }

                    $data = $this->mapRow($header, $row);

                    try {
                        $this->importRow($organisation, $actor, $data, $result);
                    } catch (ValidationException $e) {
                        $result->skip($rowNumber, implode(' ', array_merge(...array_values($e->errors()))));
                    }
                }
            });
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /** @return resource */
    public function template()
    {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, self::COLUMNS);
        rewind($handle);

        return $handle;
    }

    /**
     * @param  resource  $handle
     * @return array<int, string>
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle);

        if ($header === false) {
            throw ValidationException::withMessages([
                'file' => 'The file is empty.',
            ]);
        }

        return array_map(
            fn (string $column): string => strtolower(trim($column)),
            $header,
        );
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string|null>  $row
     * @return array<string, string|null>
     */
    private function mapRow(array $header, array $row): array
    {
        $data = [];

        foreach ($header as $index => $column) {
            $value = $row[$index] ?? null;
            $value = $value === null ? null : trim($value);
            $data[$column] = $value === '' ? null : $value;
        }

        return $data;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlank(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function importRow(Organisation $organisation, ?User $actor, array $data, ClubMemberImportResult $result): void
    {
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;

        if (! filled($firstName) || ! filled($lastName)) {
            throw ValidationException::withMessages(['row' => ['first_name and last_name are required.']]);
        }

        $category = ClubMemberCategory::tryFrom(strtolower((string) ($data['category'] ?? 'senior')))
            ?? ClubMemberCategory::Senior;
        $status = ClubMemberStatus::tryFrom(strtolower((string) ($data['status'] ?? 'active')))
            ?? ClubMemberStatus::Active;

        $email = $data['email'] !== null ? strtolower($data['email']) : null;
        $memberNumber = $data['member_number'];

        $existing = $this->findExisting($organisation, $memberNumber, $email);

        $payload = [
            'organisation_id' => $organisation->id,
            'member_number' => $memberNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $data['phone'],
            'date_of_birth' => $this->parseDate($data['date_of_birth']),
            'category' => $category,
            'joined_at' => $this->parseDate($data['joined_at']),
            'expires_at' => $this->parseDate($data['expires_at']),
            'emergency_contact_name' => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
        ];

        if ($existing !== null) {
            $existing->forceFill($payload)->save();

            if ($existing->status !== $status) {
                $existing->transitionTo($status, $actor, 'CSV import');
            }

            $result->updated++;

            return;
        }

        $member = new ClubMember($payload);
        $member->status = $status;
        $member->save();

        $member->statusChanges()->create([
            'from_status' => null,
            'to_status' => $status->value,
            'changed_by' => $actor?->id,
            'reason' => 'CSV import',
        ]);

        $result->created++;
    }

    private function findExisting(Organisation $organisation, ?string $memberNumber, ?string $email): ?ClubMember
    {
        if (filled($memberNumber)) {
            $match = ClubMember::query()
                ->where('organisation_id', $organisation->id)
                ->where('member_number', $memberNumber)
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        if (filled($email)) {
            return ClubMember::query()
                ->where('organisation_id', $organisation->id)
                ->where('email', $email)
                ->first();
        }

        return null;
    }

    private function parseDate(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => ['Could not parse date: '.$value]]);
        }
    }
}

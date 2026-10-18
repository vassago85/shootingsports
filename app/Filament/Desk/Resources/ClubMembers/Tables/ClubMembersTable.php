<?php

namespace App\Filament\Desk\Resources\ClubMembers\Tables;

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Mail\ClubApplicationDecisionMail;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Services\ClubMembers\ImportClubMembers;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClubMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('member_number')
                    ->label('No.')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('last_name')
                    ->label('Name')
                    ->getStateUsing(fn (ClubMember $record): string => $record->fullName())
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('last_name', $direction)
                        ->orderBy('first_name', $direction))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(function (Builder $inner) use ($search): void {
                            $like = '%'.addcslashes($search, '%_\\').'%';
                            $inner->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        })),
                TextColumn::make('category')
                    ->badge(),
                TextColumn::make('effectiveStatus')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (ClubMember $record): ClubMemberStatus => $record->effectiveStatus()),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('j M Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('organisation.name')
                    ->label('Club')
                    ->visible(fn (): bool => self::managesMultipleClubs())
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('email')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ClubMemberStatus::class),
                SelectFilter::make('category')->options(ClubMemberCategory::class),
                Filter::make('expiring_soon')
                    ->label('Expiring in 30 days')
                    ->query(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false)
                        ? $query->expiringWithin(30)
                        : $query),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ClubMember $record): bool => $record->status === ClubMemberStatus::Applied)
                    ->requiresConfirmation()
                    ->action(function (ClubMember $record): void {
                        $record->transitionTo(ClubMemberStatus::Active, Auth::user(), 'Approved from Desk');
                        $record->forceFill([
                            'joined_at' => $record->joined_at ?? now()->toDateString(),
                            'expires_at' => $record->expires_at ?? now()->addYear()->toDateString(),
                        ])->save();

                        self::notifyApplicant($record, approved: true);
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ClubMember $record): bool => $record->status === ClubMemberStatus::Applied)
                    ->schema([
                        Textarea::make('reason')
                            ->required()
                            ->maxLength(1000)
                            ->label('Why is this application rejected?')
                            ->helperText('The reason is emailed to the applicant.'),
                    ])
                    ->action(function (ClubMember $record, array $data): void {
                        $reason = (string) ($data['reason'] ?? '');
                        $record->transitionTo(ClubMemberStatus::Rejected, Auth::user(), $reason);

                        self::notifyApplicant($record, approved: false, reason: $reason);
                    }),
                Action::make('suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn (ClubMember $record): bool => $record->status === ClubMemberStatus::Active)
                    ->schema([
                        Textarea::make('reason')->maxLength(1000),
                    ])
                    ->action(function (ClubMember $record, array $data): void {
                        $record->transitionTo(ClubMemberStatus::Suspended, Auth::user(), (string) ($data['reason'] ?? ''));
                    }),
                Action::make('reinstate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (ClubMember $record): bool => in_array($record->status, [ClubMemberStatus::Suspended, ClubMemberStatus::Resigned, ClubMemberStatus::Rejected], true))
                    ->requiresConfirmation()
                    ->action(function (ClubMember $record): void {
                        $record->transitionTo(ClubMemberStatus::Active, Auth::user(), 'Reinstated from Desk');
                    }),
                Action::make('resign')
                    ->label('Mark resigned')
                    ->icon('heroicon-o-user-minus')
                    ->color('gray')
                    ->visible(fn (ClubMember $record): bool => in_array($record->status, [ClubMemberStatus::Active, ClubMemberStatus::Suspended], true))
                    ->schema([
                        Textarea::make('reason')->maxLength(1000),
                    ])
                    ->action(function (ClubMember $record, array $data): void {
                        $record->transitionTo(ClubMemberStatus::Resigned, Auth::user(), (string) ($data['reason'] ?? ''));
                    }),
                EditAction::make(),
            ])
            ->headerActions([
                Action::make('import')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema(fn (): array => [
                        Select::make('organisation_id')
                            ->label('Club')
                            ->options(fn (): array => Organisation::query()
                                ->whereIn('id', self::importableClubIds())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required(),
                        FileUpload::make('file')
                            ->label('CSV file')
                            ->disk('local')
                            ->directory('club-member-imports')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data, ImportClubMembers $importer): void {
                        $organisation = Organisation::query()->findOrFail((int) $data['organisation_id']);

                        abort_unless(Auth::user()?->canManageClubMembers($organisation), 403);

                        $path = Storage::disk('local')->path($data['file']);
                        $result = $importer->handle($organisation, $path, Auth::user());

                        $notification = Notification::make()
                            ->title('Member import finished')
                            ->body($result->summary());

                        if ($result->errors !== []) {
                            $notification->warning();
                        } else {
                            $notification->success();
                        }

                        $notification->send();
                    }),
                Action::make('import_template')
                    ->label('Download template')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(fn (): StreamedResponse => response()->streamDownload(
                        function (): void {
                            $out = fopen('php://output', 'w');
                            fputcsv($out, ImportClubMembers::COLUMNS);
                            fclose($out);
                        },
                        'club-members-template.csv',
                        ['Content-Type' => 'text/csv'],
                    )),
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (Table $table): StreamedResponse => self::exportCsv($table)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('renew')
                        ->label('Renew to year-end')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Set these members to Active with expiry on each club\'s configured year-end. Lapsed and expired members are reactivated.')
                        ->action(function (Collection $records): void {
                            $renewed = 0;

                            foreach ($records as $member) {
                                /** @var ClubMember $member */
                                $expiresAt = $member->organisation->membershipYearEndFor(CarbonImmutable::today());
                                $member->renew($expiresAt, Auth::user(), 'Bulk renew from Desk');
                                $renewed++;
                            }

                            Notification::make()
                                ->title($renewed === 1 ? '1 member renewed.' : $renewed.' members renewed.')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->schema([
                            Textarea::make('reason')
                                ->required()
                                ->maxLength(1000),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $reason = (string) ($data['reason'] ?? '');
                            $actor = Auth::user();

                            foreach ($records as $member) {
                                /** @var ClubMember $member */
                                if ($member->status === ClubMemberStatus::Active) {
                                    $member->transitionTo(ClubMemberStatus::Suspended, $actor, $reason);
                                }
                            }

                            Notification::make()->title('Selected members suspended.')->success()->send();
                        }),
                    BulkAction::make('resign')
                        ->label('Mark resigned')
                        ->icon('heroicon-o-user-minus')
                        ->color('gray')
                        ->schema([
                            Textarea::make('reason')
                                ->required()
                                ->maxLength(1000),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $reason = (string) ($data['reason'] ?? '');
                            $actor = Auth::user();

                            foreach ($records as $member) {
                                /** @var ClubMember $member */
                                if (in_array($member->status, [ClubMemberStatus::Active, ClubMemberStatus::Suspended, ClubMemberStatus::Lapsed], true)) {
                                    $member->transitionTo(ClubMemberStatus::Resigned, $actor, $reason);
                                }
                            }

                            Notification::make()->title('Selected members marked resigned.')->success()->send();
                        }),
                ]),
            ]);
    }

    private static function managesMultipleClubs(): bool
    {
        $user = Auth::user();

        if ($user?->is_staff) {
            return true;
        }

        return count(ClubMemberResource::managedClubIds()) > 1;
    }

    /** @return list<int> */
    private static function importableClubIds(): array
    {
        $user = Auth::user();

        if ($user?->is_staff) {
            return Organisation::query()->clubs()->pluck('id')->all();
        }

        return ClubMemberResource::managedClubIds();
    }

    private static function notifyApplicant(ClubMember $member, bool $approved, ?string $reason = null): void
    {
        if (! filled($member->email)) {
            return;
        }

        Mail::to($member->email)->queue(new ClubApplicationDecisionMail($member, $approved, $reason));
    }

    private static function exportCsv(Table $table): StreamedResponse
    {
        $query = $table->getFilteredSortedTableQuery();
        $timestamp = CarbonImmutable::now()->format('Ymd-His');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'club', 'member_number', 'first_name', 'last_name', 'email', 'phone',
                'date_of_birth', 'category', 'status', 'joined_at', 'expires_at',
                'emergency_contact_name', 'emergency_contact_phone',
            ]);

            $query->with('organisation')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $member) {
                    /** @var ClubMember $member */
                    fputcsv($out, [
                        $member->organisation?->name,
                        $member->member_number,
                        $member->first_name,
                        $member->last_name,
                        $member->email,
                        $member->phone,
                        $member->date_of_birth?->toDateString(),
                        $member->category->value,
                        $member->effectiveStatus()->value,
                        $member->joined_at?->toDateString(),
                        $member->expires_at?->toDateString(),
                        $member->emergency_contact_name,
                        $member->emergency_contact_phone,
                    ]);
                }
            });

            fclose($out);
        }, 'club-members-'.$timestamp.'.csv', ['Content-Type' => 'text/csv']);
    }
}

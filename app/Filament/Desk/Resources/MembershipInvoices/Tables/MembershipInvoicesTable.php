<?php

namespace App\Filament\Desk\Resources\MembershipInvoices\Tables;

use App\Enums\MembershipInvoicePaymentMethod;
use App\Enums\MembershipInvoiceStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Services\Memberships\IssueAnnualMembershipInvoices;
use App\Services\Memberships\RecordMembershipPayment;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MembershipInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('No.')
                    ->sortable(),
                TextColumn::make('clubMember.first_name')
                    ->label('Member')
                    ->getStateUsing(fn (MembershipInvoice $record): string => $record->clubMember?->fullName() ?? '—')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('organisation.name')
                    ->label('Club')
                    ->visible(fn (): bool => self::managesMultipleClubs())
                    ->toggleable(),
                TextColumn::make('period_start')
                    ->label('Period')
                    ->getStateUsing(fn (MembershipInvoice $record): string => $record->period_start->format('j M Y').' – '.$record->period_end->format('j M Y'))
                    ->sortable(),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->getStateUsing(fn (MembershipInvoice $record): string => 'R'.number_format($record->amount_cents / 100, 2))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('payment_method')
                    ->label('Paid via')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('paid_at')
                    ->label('Paid at')
                    ->date('j M Y')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('due_at')
                    ->label('Due')
                    ->date('j M Y')
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(MembershipInvoiceStatus::class),
                SelectFilter::make('payment_method')->options(MembershipInvoicePaymentMethod::class),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (MembershipInvoice $record): bool => $record->isOutstanding())
                    ->schema([
                        Select::make('payment_method')
                            ->options(MembershipInvoicePaymentMethod::class)
                            ->default(MembershipInvoicePaymentMethod::Eft->value)
                            ->required(),
                        TextInput::make('reference')
                            ->label('Reference (optional)')
                            ->maxLength(120),
                    ])
                    ->action(function (MembershipInvoice $record, array $data, RecordMembershipPayment $recorder): void {
                        $method = MembershipInvoicePaymentMethod::from((string) $data['payment_method']);
                        $recorder($record, $method, (string) ($data['reference'] ?? '') ?: null, Auth::user());

                        Notification::make()
                            ->title('Invoice marked paid.')
                            ->success()
                            ->send();
                    }),
                Action::make('void')
                    ->label('Void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MembershipInvoice $record): bool => $record->isOutstanding())
                    ->schema([
                        Textarea::make('reason')->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->action(function (MembershipInvoice $record, array $data): void {
                        $record->forceFill([
                            'status' => MembershipInvoiceStatus::Void,
                            'notes' => trim((string) ($record->notes ?? '')."\nVoid: ".(string) ($data['reason'] ?? '')),
                        ])->save();

                        Notification::make()->title('Invoice voided.')->success()->send();
                    }),
                Action::make('waive')
                    ->label('Waive')
                    ->icon('heroicon-o-hand-raised')
                    ->color('warning')
                    ->visible(fn (MembershipInvoice $record): bool => $record->isOutstanding())
                    ->schema([
                        Textarea::make('reason')->required()->maxLength(500),
                    ])
                    ->action(function (MembershipInvoice $record, array $data, RecordMembershipPayment $recorder): void {
                        // Waive flows through the same recorder so the
                        // member row still renews — the only diff is
                        // the payment method tag on the ledger.
                        $recorder(
                            $record,
                            MembershipInvoicePaymentMethod::Waive,
                            null,
                            Auth::user(),
                        );

                        $record->forceFill([
                            'status' => MembershipInvoiceStatus::Waived,
                            'notes' => trim((string) ($record->notes ?? '')."\nWaived: ".(string) $data['reason']),
                        ])->save();

                        Notification::make()->title('Invoice waived.')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->headerActions([
                Action::make('issueAnnualBatch')
                    ->label('Issue annual batch')
                    ->icon('heroicon-o-rectangle-stack')
                    ->color('primary')
                    ->schema([
                        Select::make('organisation_id')
                            ->label('Club')
                            ->options(fn (): array => Organisation::query()
                                ->whereIn('id', self::managedClubIds())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required(),
                        TextInput::make('year')
                            ->numeric()
                            ->minValue(2020)
                            ->maxValue(2100)
                            ->default(fn (): int => (int) CarbonImmutable::today()->year)
                            ->required(),
                    ])
                    ->action(function (array $data, IssueAnnualMembershipInvoices $issuer): void {
                        $organisation = Organisation::query()->findOrFail((int) $data['organisation_id']);
                        $year = (int) $data['year'];

                        $start = CarbonImmutable::create($year, 1, 1);
                        $end = CarbonImmutable::create($year, 12, 31);

                        $result = $issuer($organisation, $start, $end, Auth::user());

                        Notification::make()
                            ->title('Annual invoices issued.')
                            ->body(sprintf('%d created, %d already existed.', $result['created'], $result['skipped']))
                            ->success()
                            ->send();
                    }),
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
    private static function managedClubIds(): array
    {
        $user = Auth::user();

        if ($user?->is_staff) {
            return Organisation::query()->clubs()->pluck('id')->all();
        }

        return ClubMemberResource::managedClubIds();
    }
}

<?php

namespace App\Filament\Resources\Users;

use App\Enums\DigestFrequency;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\Province;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\MdRequestApproved;
use App\Notifications\MdRequestRejected;
use App\Services\Paystack\SubscriptionApplier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                Select::make('home_province')
                    ->options(Province::class),
                TextInput::make('travel_radius_km')
                    ->numeric(),
                Select::make('digest_frequency')
                    ->options(DigestFrequency::class)
                    ->default('none')
                    ->required(),
                Toggle::make('is_match_director')
                    ->label('Match director')
                    ->helperText('Grants /desk access. Prefer the "Approve MD request" record action so timestamps + email fire correctly — this toggle bypasses that flow.')
                    ->required(),
                Toggle::make('is_staff')
                    ->helperText('Full admin. Trumps every other flag.')
                    ->required(),

                // MD review workflow — read-only mirror. Use the
                // approve/reject record actions to change state so
                // notifications + enquiry linkage fire correctly.
                Placeholder::make('md_status')
                    ->label('MD review status')
                    ->content(fn (?User $record): string => match ($record?->mdStatus()) {
                        'approved' => 'Approved — /desk access granted',
                        'pending' => 'Pending review — awaiting staff decision',
                        'rejected' => 'Rejected — user can re-apply',
                        default => 'Never applied',
                    }),
                Placeholder::make('md_requested_at')
                    ->label('Requested at')
                    ->content(fn (?User $record): string => $record?->md_requested_at?->format('j M Y H:i') ?? '—'),
                Placeholder::make('md_approved_at')
                    ->label('Approved at')
                    ->content(fn (?User $record): string => $record?->md_approved_at?->format('j M Y H:i') ?? '—'),
                Placeholder::make('md_rejected_at')
                    ->label('Rejected at')
                    ->content(fn (?User $record): string => $record?->md_rejected_at?->format('j M Y H:i') ?? '—'),
                Placeholder::make('md_rejection_reason')
                    ->label('Rejection reason')
                    ->content(fn (?User $record): string => $record?->md_rejection_reason ?? '—'),

                // Subscription section — read-only mirror of what
                // Paystack has stored. To change plan state use the
                // "Grant Pro comp" record action, or fix it in the
                // Paystack dashboard and let the webhook update us.
                Placeholder::make('subscription_status')
                    ->label('Subscription status')
                    ->content(fn (?User $record): string => $record?->subscriptionStatusLabel() ?? '—'),
                Placeholder::make('plan_billing_cycle')
                    ->label('Billing cycle')
                    ->content(fn (?User $record): string => $record?->plan_billing_cycle ?? '—'),
                Placeholder::make('plan_expires_at')
                    ->label('Plan expires / next billing')
                    ->content(fn (?User $record): string => $record?->plan_expires_at?->format('j M Y H:i') ?? '—'),
                Placeholder::make('plan_cancelled_at')
                    ->label('Cancelled at')
                    ->content(fn (?User $record): string => $record?->plan_cancelled_at?->format('j M Y H:i') ?? '—'),
                Placeholder::make('paystack_customer_code')
                    ->label('Paystack customer')
                    ->content(fn (?User $record): string => $record?->paystack_customer_code ?? '—'),
                Placeholder::make('paystack_subscription_code')
                    ->label('Paystack subscription')
                    ->content(fn (?User $record): string => $record?->paystack_subscription_code ?? '—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('home_province')
                    ->badge()
                    ->searchable(),
                TextColumn::make('travel_radius_km')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('digest_frequency')
                    ->badge()
                    ->searchable(),
                IconColumn::make('is_match_director')
                    ->label('MD')
                    ->boolean(),
                TextColumn::make('md_status')
                    ->label('MD status')
                    ->badge()
                    ->state(fn (User $record): string => $record->mdStatus())
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('md_requested_at')
                    ->label('Requested')
                    ->dateTime('j M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_staff')
                    ->boolean(),
                TextColumn::make('plan')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('plan_expires_at')
                    ->label('Pro until')
                    ->dateTime('j M Y')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('md_status')
                    ->label('MD status')
                    ->placeholder('All users')
                    ->options([
                        'pending' => 'Pending review',
                        'approved' => 'Approved (MDs)',
                        'rejected' => 'Rejected',
                        'none' => 'Never applied',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'pending' => $query->whereNotNull('md_requested_at')
                                ->where('is_match_director', false)
                                ->whereNull('md_rejected_at'),
                            'approved' => $query->where('is_match_director', true),
                            'rejected' => $query->whereNotNull('md_rejected_at')
                                ->where('is_match_director', false),
                            'none' => $query->whereNull('md_requested_at'),
                            default => $query,
                        };
                    }),
                TernaryFilter::make('is_staff')
                    ->label('Staff')
                    ->placeholder('All users')
                    ->trueLabel('Staff only')
                    ->falseLabel('Non-staff only'),
                TernaryFilter::make('paystack_subscription_code')
                    ->label('Paid subscribers')
                    ->placeholder('All users')
                    ->trueLabel('Has active Paystack sub')
                    ->falseLabel('No active Paystack sub')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('paystack_subscription_code'),
                        false: fn ($query) => $query->whereNull('paystack_subscription_code'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('approveMdRequest')
                    ->label('Approve MD request')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->isMdPending() || $record->isMdRejected())
                    ->requiresConfirmation()
                    ->modalHeading('Approve this match director request?')
                    ->modalDescription('This grants /desk access, stamps md_approved_at, closes the linked MD signup enquiry, and emails the applicant.')
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'is_match_director' => true,
                            'md_approved_at' => now(),
                            // Re-approvals clear a prior rejection so the
                            // audit trail reads: applied → rejected →
                            // approved without leaving stale reject state.
                            'md_rejected_at' => null,
                            'md_rejection_reason' => null,
                        ])->save();

                        self::closeLinkedMdSignupEnquiries($record, EnquiryStatus::Closed);

                        $record->notify(new MdRequestApproved($record));

                        Notification::make()
                            ->title('MD request approved')
                            ->body("{$record->name} now has /desk access and has been emailed.")
                            ->success()
                            ->send();
                    }),
                Action::make('rejectMdRequest')
                    ->label('Reject MD request')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->isMdPending())
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason (optional — shown to applicant)')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('e.g. Could not verify link to the club named. Please reply with more detail if you want us to reconsider.'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject this match director request?')
                    ->modalDescription('The applicant keeps their shooter account. They will be emailed the reason if you provide one and can re-apply later.')
                    ->action(function (User $record, array $data): void {
                        $reason = trim((string) ($data['reason'] ?? '')) ?: null;

                        $record->forceFill([
                            'is_match_director' => false,
                            'md_rejected_at' => now(),
                            'md_rejection_reason' => $reason,
                        ])->save();

                        self::closeLinkedMdSignupEnquiries($record, EnquiryStatus::Closed);

                        $record->notify(new MdRequestRejected($record, $reason));

                        Notification::make()
                            ->title('MD request rejected')
                            ->body("{$record->name} has been emailed. They can re-apply from /directors/register.")
                            ->warning()
                            ->send();
                    }),
                Action::make('grantProComp')
                    ->label('Grant Pro comp')
                    ->icon(Heroicon::OutlinedGift)
                    ->color('warning')
                    ->form([
                        TextInput::make('months')
                            ->label('Months of Pro to grant')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->default(3)
                            ->helperText('Adds to the existing expiry if the user is already Pro.'),
                    ])
                    ->action(function (User $record, array $data): void {
                        // No Paystack call — this is a manual grant, so
                        // it never touches customer/subscription/auth
                        // codes and never auto-renews.
                        app(SubscriptionApplier::class)
                            ->applyManualGrant($record, (int) $data['months']);

                        Notification::make()
                            ->title('Pro comp granted')
                            ->body("{$record->name} is Pro until {$record->fresh()->plan_expires_at?->format('j M Y')}.")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Close any open MdSignup enquiries linked to this user so the
     * staff inbox does not carry stale "pending review" rows after
     * the decision has been made. Idempotent — safe to call twice.
     */
    private static function closeLinkedMdSignupEnquiries(User $user, EnquiryStatus $status): void
    {
        Enquiry::query()
            ->where('type', EnquiryType::MdSignup)
            ->where('user_id', $user->id)
            ->whereIn('status', [EnquiryStatus::New->value, EnquiryStatus::Read->value])
            ->update(['status' => $status->value]);
    }
}

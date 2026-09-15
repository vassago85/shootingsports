<?php

namespace App\Filament\Resources\Users;

use App\Enums\DigestFrequency;
use App\Enums\Province;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Services\Paystack\SubscriptionApplier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->helperText('Grants /desk access. Set automatically when a user signs up via /directors/register.')
                    ->required(),
                Toggle::make('is_staff')
                    ->helperText('Full admin. Trumps every other flag.')
                    ->required(),

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
                TernaryFilter::make('is_match_director')
                    ->label('Match directors')
                    ->placeholder('All users')
                    ->trueLabel('MDs only')
                    ->falseLabel('Non-MDs only'),
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
}

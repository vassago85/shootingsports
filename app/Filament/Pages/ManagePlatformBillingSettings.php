<?php

namespace App\Filament\Pages;

use App\Support\PlatformSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManagePlatformBillingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-platform-billing-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Platform billing';

    protected static ?string $title = 'Platform billing';

    /**
     * Reached from the Settings landing page, not the top-level nav.
     * Route stays live so bookmarks and the Settings page link resolve.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_staff;
    }

    public function mount(): void
    {
        $this->form->fill(PlatformSettings::details());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Platform fees (transparent to members)')
                    ->description('Changes apply immediately — no redeploy. Shown on every member receipt.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('fee_bps')
                            ->label('Platform fee (basis points)')
                            ->numeric()->minValue(0)->maxValue(10000)
                            ->helperText('200 = 2%. Applied to the next transaction.'),
                        TextInput::make('vat_bps')
                            ->label('VAT rate (basis points)')
                            ->numeric()->minValue(0)->maxValue(10000)
                            ->helperText('1500 = 15%.'),
                        TextInput::make('payout_admin_fee_cents')
                            ->label('Payout admin fee (cents, excl. VAT)')
                            ->numeric()->minValue(0)
                            ->helperText('5000 = R50. Applied to the 2nd and later payouts per calendar month on the bank-transfer path.'),
                    ]),

                Section::make('Trial & grace')
                    ->columns(2)
                    ->schema([
                        TextInput::make('trial_days')->numeric()->minValue(0)->maxValue(90),
                        TextInput::make('grace_window_days')->numeric()->minValue(0)->maxValue(90),
                    ]),

                Section::make('Single-event product')
                    ->schema([
                        TextInput::make('single_event_price_cents')
                            ->label('Price (cents, excl. VAT)')
                            ->numeric()->minValue(0)
                            ->helperText('75000 = R750.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        PlatformSettings::save($this->form->getState());

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}

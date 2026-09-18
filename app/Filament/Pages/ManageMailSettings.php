<?php

namespace App\Filament\Pages;

use App\Support\MailSettings;
use App\Support\TurnstileSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

class ManageMailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-mail-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Email & Turnstile';

    protected static ?string $title = 'Email & Turnstile';

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
        $settings = array_merge(MailSettings::details(), TurnstileSettings::details());
        $settings['mailgun_secret'] = '';
        $settings['secret_key'] = '';

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Delivery')
                    ->description('How the site sends mail. Changes apply immediately — no redeploy.')
                    ->columns(2)
                    ->schema([
                        Select::make('mailer')
                            ->label('Mail driver')
                            ->options([
                                'log' => 'Log (laravel.log — testing)',
                                'mailgun' => 'Mailgun (API)',
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('from_address')
                            ->label('From address')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('from_name')
                            ->label('From name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Mailgun')
                    ->description('From your Mailgun dashboard. Secret is stored encrypted.')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('mailer') === 'mailgun')
                    ->schema([
                        TextInput::make('mailgun_domain')
                            ->label('Domain')
                            ->placeholder('mg.shootingsports.co.za')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('mailer') === 'mailgun'),
                        TextInput::make('mailgun_endpoint')
                            ->label('API endpoint')
                            ->helperText('api.eu.mailgun.net (EU) or api.mailgun.net (US).')
                            ->default('api.eu.mailgun.net')
                            ->maxLength(255),
                        TextInput::make('mailgun_secret')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder('Leave blank to keep the current key')
                            ->columnSpanFull(),
                    ]),

                Section::make('Cloudflare Turnstile')
                    ->description('Bot protection for the coming-soon interest form. Keys from the Cloudflare Turnstile dashboard. Secret is stored encrypted. Leave both blank locally to skip the widget.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_key')
                            ->label('Site key')
                            ->helperText('Public key — shown in the widget on the coming-soon page.')
                            ->maxLength(255)
                            ->placeholder('0x…'),
                        TextInput::make('secret_key')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder('Leave blank to keep the current key')
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        MailSettings::save($state);
        TurnstileSettings::save($state);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->schema([
                    TextInput::make('recipient')
                        ->label('Send to')
                        ->email()
                        ->required()
                        ->default(fn (): ?string => auth()->user()?->email),
                ])
                ->action(function (array $data): void {
                    try {
                        Mail::raw(
                            "Test email from Shooting Sports.\n\nIf you received this, Mailgun (or the active mailer) is working.",
                            fn ($message) => $message
                                ->to($data['recipient'])
                                ->subject('Shooting Sports — test email'),
                        );
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Test email failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Test email sent')
                        ->body("Sent to {$data['recipient']}. Save settings first if you just changed them.")
                        ->success()
                        ->send();
                }),
        ];
    }
}

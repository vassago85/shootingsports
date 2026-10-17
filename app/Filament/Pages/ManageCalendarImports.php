<?php

namespace App\Filament\Pages;

use App\Enums\ImportFrequency;
use App\Imports\CalendarImportRunner;
use App\Support\CalendarImportSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageCalendarImports extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-calendar-imports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Imports';

    protected static ?string $title = 'Imports';

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
        $this->form->fill(collect(CalendarImportSettings::sources())
            ->mapWithKeys(fn (array $source): array => [$source['key'] => CalendarImportSettings::frequency($source['key'])->value])
            ->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(collect(CalendarImportSettings::sources())
                ->map(fn (array $source): Section => Section::make($source['name'])
                    ->description(fn (): string => $this->lastRunSummary($source['key']))
                    ->afterHeader([
                        Action::make('run_'.$source['key'])
                            ->label('Run now')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->color('gray')
                            ->action(fn () => $this->runImport($source['key'])),
                    ])
                    ->schema([
                        Select::make($source['key'])
                            ->label('Runs')
                            ->options(ImportFrequency::class)
                            ->helperText($source['url'])
                            ->required(),
                    ]))
                ->all())
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (CalendarImportSettings::sources() as $source) {
            CalendarImportSettings::setFrequency($source['key'], $state[$source['key']]);
        }

        Notification::make()
            ->title('Import schedule saved')
            ->success()
            ->send();
    }

    public function runImport(string $key): void
    {
        $source = CalendarImportSettings::source($key);

        if ($source === null) {
            return;
        }

        $run = app(CalendarImportRunner::class)->run($key);

        Notification::make()
            ->title($source['name'])
            ->body($run->summary())
            ->status($run->failed() ? 'danger' : 'success')
            ->send();
    }

    private function lastRunSummary(string $key): string
    {
        $run = app(CalendarImportRunner::class)->latestRun($key);

        return $run === null ? 'Not run yet.' : 'Last run '.$run->summary();
    }
}

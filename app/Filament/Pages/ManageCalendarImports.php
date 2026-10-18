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
use Illuminate\Support\HtmlString;
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
                    ->description(fn (): string => $this->runStatusLine($source['key']))
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
                            // Save the frequency the moment staff pick a new
                            // value instead of making them hunt for a Save
                            // button at the bottom of a long list of imports.
                            ->live()
                            ->afterStateUpdated(fn (mixed $state) => $this->persistFrequency($source['key'], $state))
                            ->helperText(new HtmlString(
                                'Source: <a href="'.e($source['url']).'" target="_blank" rel="noopener" class="fi-link">'.e($source['url']).'</a>',
                            ))
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

    private function persistFrequency(string $key, mixed $value): void
    {
        $frequency = match (true) {
            $value instanceof ImportFrequency => $value,
            is_string($value) => ImportFrequency::tryFrom($value),
            default => null,
        };

        if ($frequency === null) {
            return;
        }

        CalendarImportSettings::setFrequency($key, $frequency);

        Notification::make()
            ->title('Saved')
            ->body(CalendarImportSettings::source($key)['name'].' now runs '.$frequency->getLabel().'.')
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

    /**
     * Combined status line: last run summary + next scheduled run. Used
     * as the Section description so staff see "when" and "what next"
     * without opening the schedule editor.
     */
    private function runStatusLine(string $key): string
    {
        $last = $this->lastRunSummary($key);
        $next = $this->nextRunSummary($key);

        return trim($last.' · '.$next, ' ·');
    }

    private function nextRunSummary(string $key): string
    {
        $frequency = CalendarImportSettings::frequency($key);

        if ($frequency === ImportFrequency::Off) {
            return 'Next run: off';
        }

        $next = now();

        for ($i = 0; $i < 400; $i++) {
            $next = $next->copy()->addDay()->startOfDay();

            if ($frequency->isDueOn($next)) {
                return 'Next run: '.$next->format('D j M');
            }
        }

        return 'Next run: '.$frequency->getLabel();
    }
}

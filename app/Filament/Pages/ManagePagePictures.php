<?php

namespace App\Filament\Pages;

use App\Support\PageHeroes;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManagePagePictures extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-page-pictures';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Page pictures';

    protected static ?string $title = 'Page pictures';

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
        $data = [];

        foreach (array_keys(PageHeroes::pages()) as $page) {
            $data[$page] = PageHeroes::path($page);
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $uploads = [];

        foreach (PageHeroes::pages() as $page => $label) {
            $uploads[] = FileUpload::make($page)
                ->label($label)
                ->disk('media')
                ->directory('page-heroes')
                ->image()
                ->maxSize(4096)
                ->helperText('JPEG, PNG, or WebP, up to 4 MB. Clear it to use the built-in photo.');
        }

        return $schema
            ->components([
                Section::make('Hero pictures')
                    ->description('These photos sit behind the heading on each public page. A wide landscape works best.')
                    ->schema($uploads),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (array_keys(PageHeroes::pages()) as $page) {
            $path = $state[$page] ?? null;

            if (is_array($path)) {
                $path = $path[0] ?? null;
            }

            PageHeroes::save($page, is_string($path) ? $path : null);
        }

        Notification::make()
            ->title('Page pictures saved')
            ->success()
            ->send();
    }
}

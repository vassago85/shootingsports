<?php

namespace App\Filament\Desk\Pages;

use App\Enums\ClaimStatus;
use App\Enums\ListingStatus;
use App\Models\Claim;
use App\Models\Organisation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ClaimListing extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?string $navigationLabel = 'Claim a listing';

    protected static string | \UnitEnum | null $navigationGroup = 'Your listings';

    protected static ?string $title = 'Claim an existing listing';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.desk.pages.claim-listing';

    /**
     * @var array{organisation_id: ?int, evidence: ?string}
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'organisation_id' => null,
            'evidence' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $userId = Auth::id();

        return $schema
            ->components([
                Select::make('organisation_id')
                    ->label('Club, series or federation')
                    ->options(
                        Organisation::query()
                            ->where('status', ListingStatus::Published)
                            ->whereDoesntHave('users', fn ($q) => $q->where('users.id', $userId))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->required()
                    ->helperText('Only published listings appear here. New ones you create yourself do not need a claim.'),
                Textarea::make('evidence')
                    ->label('Why should we grant you access?')
                    ->rows(4)
                    ->required()
                    ->helperText('Role at the club/series, contact email on their website, or anything that helps staff verify you.'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        $user = Auth::user();

        $organisation = Organisation::query()->findOrFail($state['organisation_id']);

        $alreadyPending = Claim::query()
            ->where('user_id', $user->id)
            ->where('claimable_type', 'organisation')
            ->where('claimable_id', $organisation->id)
            ->where('status', ClaimStatus::Pending)
            ->exists();

        if ($alreadyPending) {
            Notification::make()
                ->title('You already have a pending claim on this listing.')
                ->warning()
                ->send();

            return;
        }

        Claim::query()->create([
            'claimable_type' => 'organisation',
            'claimable_id' => $organisation->id,
            'user_id' => $user->id,
            'evidence' => $state['evidence'],
            'status' => ClaimStatus::Pending,
        ]);

        $this->form->fill([
            'organisation_id' => null,
            'evidence' => null,
        ]);

        Notification::make()
            ->title('Claim submitted')
            ->body('Staff will review it. You will be able to edit the listing once approved.')
            ->success()
            ->send();
    }
}

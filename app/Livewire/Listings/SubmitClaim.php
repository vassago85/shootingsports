<?php

namespace App\Livewire\Listings;

use App\Enums\ClaimStatus;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Mail\ListingClaimSubmittedMail;
use App\Models\Claim;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Support\StaffInbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Public claim for a staff-listed club, range, or match.
 * Guests see how to register first. A signed-in person submits
 * evidence, and a site admin approves it from the claims queue.
 */
class SubmitClaim extends Component
{
    public Organisation|Venue $listing;

    /**
     * club, range, or match. A match claim is filed against the host club.
     */
    public string $kind = '';

    public string $subjectName = '';

    public ?string $matchTitle = null;

    public string $subtitle = '';

    public string $approvalNote = '';

    #[Validate('required|string|min:20|max:2000')]
    public string $evidence = '';

    /**
     * Why the form is hidden: guest, taken, or pending.
     */
    public ?string $blocked = null;

    public function mount(string $type, string $slug): void
    {
        $resolved = $this->resolve($type, $slug);

        $this->kind = $resolved['kind'];
        $this->listing = $resolved['listing'];
        $this->subjectName = $resolved['subjectName'];
        $this->matchTitle = $resolved['matchTitle'];
        $this->subtitle = $resolved['subtitle'];
        $this->approvalNote = $resolved['approvalNote'];

        $user = Auth::user();

        if ($this->listing->claimed_by !== null) {
            $this->blocked = 'taken';

            return;
        }

        if ($user === null) {
            session()->put('url.intended', url()->current());
            $this->blocked = 'guest';

            return;
        }

        if ($this->pendingClaim($user->id)->exists()) {
            $this->blocked = 'pending';
        }
    }

    public function submit(): void
    {
        $user = Auth::user();

        if ($user === null || $this->blocked !== null) {
            return;
        }

        if ($this->listing->claimed_by !== null) {
            $this->blocked = 'taken';

            return;
        }

        if ($this->pendingClaim($user->id)->exists()) {
            $this->blocked = 'pending';

            return;
        }

        $this->validate();

        $claim = Claim::query()->create([
            'claimable_type' => $this->listing instanceof Organisation ? 'organisation' : 'venue',
            'claimable_id' => $this->listing->id,
            'user_id' => $user->id,
            'evidence' => trim($this->evidence),
            'status' => ClaimStatus::Pending,
        ]);

        StaffInbox::queue(
            new ListingClaimSubmittedMail($claim, $this->subjectName, $this->kind),
            $user->email,
        );

        $this->blocked = 'pending';
        $this->evidence = '';
    }

    public function render(): View
    {
        return view('livewire.listings.submit-claim')
            ->layout('components.layouts.public', [
                'title' => 'Claim '.$this->subjectName,
            ]);
    }

    /**
     * @return array{kind: string, listing: Organisation|Venue, subjectName: string, matchTitle: ?string, subtitle: string, approvalNote: string}
     */
    private function resolve(string $type, string $slug): array
    {
        return match ($type) {
            'club' => $this->resolveClub($slug),
            'range' => $this->resolveRange($slug),
            'match' => $this->resolveMatch($slug),
            default => abort(404),
        };
    }

    /**
     * @return array{kind: string, listing: Organisation, subjectName: string, matchTitle: null, subtitle: string, approvalNote: string}
     */
    private function resolveClub(string $slug): array
    {
        $organisation = $this->publishedOrganisation($slug);

        return [
            'kind' => 'club',
            'listing' => $organisation,
            'subjectName' => $organisation->name,
            'matchTitle' => null,
            'subtitle' => $this->placeLine($organisation),
            'approvalNote' => 'A site admin approves the claim before you can manage this listing and its matches.',
        ];
    }

    /**
     * @return array{kind: string, listing: Venue, subjectName: string, matchTitle: null, subtitle: string, approvalNote: string}
     */
    private function resolveRange(string $slug): array
    {
        $venue = Venue::query()->where('slug', $slug)->firstOrFail();
        abort_unless($venue->status === ListingStatus::Published, 404);

        return [
            'kind' => 'range',
            'listing' => $venue,
            'subjectName' => $venue->name,
            'matchTitle' => null,
            'subtitle' => $this->placeLine($venue),
            'approvalNote' => 'A site admin approves the claim. You are then recorded as the person responsible for this range.',
        ];
    }

    /**
     * @return array{kind: string, listing: Organisation, subjectName: string, matchTitle: string, subtitle: string, approvalNote: string}
     */
    private function resolveMatch(string $slug): array
    {
        $event = Event::query()->where('slug', $slug)->firstOrFail();
        abort_if($event->status === EventStatus::Draft, 404);

        $host = $event->hostOrganisation;
        abort_if($host === null, 404);
        abort_unless($host->status === ListingStatus::Published, 404);

        return [
            'kind' => 'match',
            'listing' => $host,
            'subjectName' => $host->name,
            'matchTitle' => $event->title,
            'subtitle' => $this->placeLine($host),
            'approvalNote' => 'You are claiming the host club. A site admin approves it before you can manage the club and this match.',
        ];
    }

    private function publishedOrganisation(string $slug): Organisation
    {
        $organisation = Organisation::query()->where('slug', $slug)->firstOrFail();
        abort_unless($organisation->status === ListingStatus::Published, 404);

        return $organisation;
    }

    private function placeLine(Organisation|Venue $listing): string
    {
        return collect([
            $listing->town,
            $listing->province?->getLabel(),
        ])->filter()->implode(' · ');
    }

    private function pendingClaim(int $userId): Builder
    {
        return Claim::query()
            ->where('user_id', $userId)
            ->where('claimable_type', $this->listing instanceof Organisation ? 'organisation' : 'venue')
            ->where('claimable_id', $this->listing->id)
            ->where('status', ClaimStatus::Pending);
    }
}

<?php

namespace App\Livewire\Matches;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\OrganisationUserRole;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Public match submission. Any signed-in, verified user can file a
 * match without desk access. The match is stored as a draft and the
 * host club as a pending listing, so neither appears on the public
 * calendar until staff run ApproveSubmittedMatch.
 */
class SubmitMatch extends Component
{
    public string $title = '';

    public string $starts_on = '';

    public string $host_name = '';

    public string $province = '';

    public string $town = '';

    public ?string $discipline_id = null;

    public string $description = '';

    public string $signup_note = '';

    public function mount(): void
    {
        $this->signup_note = (string) Session::get('md.pending_host', '');
    }

    public function submit(): void
    {
        $user = Auth::user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        if ($this->discipline_id === '') {
            $this->discipline_id = null;
        }

        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'host_name' => ['required', 'string', 'min:3', 'max:160'],
            'province' => ['required', 'string', Rule::enum(Province::class)],
            'town' => ['nullable', 'string', 'max:120'],
            'discipline_id' => ['nullable', 'integer', 'exists:disciplines,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'title' => 'match title',
            'starts_on' => 'date',
            'host_name' => 'club or series',
            'discipline_id' => 'sport',
        ]);

        $event = DB::transaction(function () use ($user): Event {
            $host = $this->resolveHost($user);
            $startsAt = Carbon::parse($this->starts_on)->startOfDay();

            $event = Event::create([
                'title' => trim($this->title),
                'host_organisation_id' => $host->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->endOfDay(),
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::Draft,
                'description' => trim($this->description) !== '' ? trim($this->description) : null,
                'created_by' => $user->id,
                'source' => ListingSource::Submission,
            ]);

            if (filled($this->discipline_id)) {
                $event->syncDisciplines([(int) $this->discipline_id], (int) $this->discipline_id);
            }

            $this->recordDirectorRequest($user, $host);

            return $event;
        });

        Session::forget('md.pending_host');
        Session::flash('status', 'Thanks. Your match is in for review. It stays off the public calendar until we approve it.');

        $this->redirect(route('matches.submit.thanks', ['event' => $event->slug]), navigate: false);
    }

    public function render()
    {
        return view('livewire.matches.submit-match', [
            'provinceOptions' => collect(Province::cases())
                ->mapWithKeys(fn (Province $province): array => [$province->value => $province->getLabel()])
                ->all(),
            'disciplineOptions' => Discipline::query()
                ->where('is_published', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ])->layout('components.layouts.public', ['title' => 'Submit a match']);
    }

    private function resolveHost(User $user): Organisation
    {
        $name = trim($this->host_name);

        $existing = Organisation::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing !== null) {
            $belongs = $user->organisations()->whereKey($existing->id)->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'host_name' => 'That club is already on the register. Staff need to add you to it before you can list its matches.',
                ]);
            }

            return $existing;
        }

        $organisation = Organisation::create([
            'name' => $name,
            'type' => OrganisationType::Club,
            'province' => $this->province,
            'town' => trim($this->town) !== '' ? trim($this->town) : null,
            'status' => ListingStatus::Pending,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Submission,
            'claimed_by' => $user->id,
            'accredited' => false,
        ]);

        $organisation->users()->attach($user->id, [
            'role' => OrganisationUserRole::MatchDirector->value,
            'granted_at' => now(),
        ]);

        return $organisation;
    }

    private function recordDirectorRequest(User $user, Organisation $host): void
    {
        if ($user->is_staff || $user->is_match_director) {
            return;
        }

        if ($user->isMdRejected() || $user->md_requested_at === null) {
            $user->forceFill([
                'md_requested_at' => now(),
                'md_rejected_at' => null,
                'md_rejection_reason' => null,
            ])->save();
        }

        $alreadyQueued = Enquiry::query()
            ->where('user_id', $user->id)
            ->where('type', EnquiryType::MdSignup)
            ->where('status', EnquiryStatus::New)
            ->exists();

        if ($alreadyQueued) {
            return;
        }

        Enquiry::create([
            'type' => EnquiryType::MdSignup,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'MD request: '.$user->name,
            'body' => $host->name,
            'context' => ['host_hint' => $host->name],
            'status' => EnquiryStatus::New,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}

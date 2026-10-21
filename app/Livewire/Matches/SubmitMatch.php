<?php

namespace App\Livewire\Matches;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EventKind;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public match submission wizard — a four-step flow (Date → Match →
 * Venue → Review) that creates a draft Event and, if the host is new,
 * a pending Organisation under the submitter's account. Both stay
 * off the public calendar until staff run ApproveSubmittedMatch.
 *
 * Design notes:
 *   - Any signed-in, verified user can submit. First-time submitters
 *     have an MdSignup enquiry filed for staff to review their
 *     director capability — see {@see self::recordDirectorRequest()}.
 *   - {@see self::next()} runs per-step validation so a shooter can't
 *     advance until the current step is clean.
 *   - {@see self::submit()} runs full-form validation and creates the
 *     records. Tests may call submit() directly after setting every
 *     field — the wizard navigation is UX, not behaviour.
 *   - The `step` property is URL-bound and pushes a history entry
 *     on each change, so the browser Back button walks the steps
 *     the shooter just left. A bookmarked or typed step cannot
 *     jump past the first step whose fields are still invalid.
 */
class SubmitMatch extends Component
{
    #[Url(as: 'step', history: true)]
    public int $step = 1;

    // Step 1 — Date
    public string $starts_on = '';

    public bool $all_day = true;

    public string $starts_at_time = '';

    public string $ends_at_time = '';

    // Step 2 — Match
    public string $title = '';

    public string $kind = 'competition';

    public ?string $discipline_id = null;

    public string $description = '';

    // Step 3 — Venue
    public string $host_name = '';

    public string $province = '';

    public string $town = '';

    // Carried forward from signup if the user ticked the director
    // box there — surfaced on the review step so they can confirm
    // the host they named at signup is the one they're really
    // submitting under.
    public string $signup_note = '';

    public const int TOTAL_STEPS = 4;

    public function mount(): void
    {
        $this->signup_note = (string) Session::get('md.pending_host', '');
    }

    /**
     * Runs after the URL attribute has copied `?step=` onto the
     * property. Clamping inside mount() is too early — that value
     * is still the default when mount() runs. Later Livewire
     * requests already carry a step the actions have checked, so
     * this only applies to the first full-page load.
     */
    public function booted(): void
    {
        if (app('livewire')->isLivewireRequest()) {
            return;
        }

        $reachable = $this->reachableStep($this->step);

        if (request()->has('step') && (int) request()->query('step') !== $reachable) {
            $this->redirect(route('events.submit', ['step' => $reachable]), navigate: false);

            return;
        }

        $this->step = $reachable;
    }

    public function updatedAllDay(bool $allDay): void
    {
        if (! $allDay) {
            return;
        }

        $this->clearHiddenTimes();
    }

    /**
     * A forged request can set `step` directly. Keep it on a step
     * the shooter has actually earned.
     */
    public function updatedStep(int $step): void
    {
        $reachable = $this->reachableStep($step);

        if ($step !== $reachable) {
            $this->step = $reachable;
        }
    }

    public function next(): void
    {
        $this->normalizeSubmissionFields();

        $this->validate(
            $this->rulesForStep($this->step),
            $this->validationMessages(),
            $this->validationAttributes(),
        );

        $this->step = min(self::TOTAL_STEPS, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goTo(int $step): void
    {
        $this->step = $this->reachableStep($step);
    }

    public function submit(): void
    {
        $user = Auth::user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        $this->normalizeSubmissionFields();

        $this->validate(
            $this->fullRules(),
            $this->validationMessages(),
            $this->validationAttributes(),
        );

        $event = DB::transaction(function () use ($user): Event {
            $host = $this->resolveHost($user);
            [$startsAt, $endsAt] = $this->computeTimestamps();

            $event = Event::create([
                'title' => trim($this->title),
                'host_organisation_id' => $host->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'all_day' => $this->all_day,
                'level' => EventLevel::Club,
                'kind' => EventKind::from($this->kind),
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

        $this->redirect(route('events.submit.thanks', ['event' => $event->slug]), navigate: false);
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
            'disciplineLabel' => $this->disciplineLabel(),
            'kindLabel' => $this->kindLabel(),
            'provinceLabel' => $this->provinceLabel(),
            'totalSteps' => self::TOTAL_STEPS,
        ])->layout('components.layouts.public', ['title' => 'Submit an event']);
    }

    /**
     * Human label for the current discipline selection — used on the
     * review step so the shooter sees "IPSC Handgun" rather than a
     * bare id, and "— None selected" when they skipped it.
     */
    public function disciplineLabel(): string
    {
        if ($this->discipline_id === null || $this->discipline_id === '') {
            return '— None selected';
        }

        return (string) (Discipline::query()->find((int) $this->discipline_id)?->name
            ?? '— Unknown discipline');
    }

    public function kindLabel(): string
    {
        return $this->kind === 'training' ? 'Training' : 'Competition';
    }

    public function provinceLabel(): string
    {
        if ($this->province === '') {
            return '— Not set';
        }

        return Province::tryFrom($this->province)?->getLabel() ?? $this->province;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'starts_on' => ['required', 'date', 'after_or_equal:today'],
                'all_day' => ['boolean'],
                'starts_at_time' => [Rule::excludeIf(fn (): bool => $this->all_day), 'nullable', 'required_if:all_day,false', 'date_format:H:i'],
                'ends_at_time' => [Rule::excludeIf(fn (): bool => $this->all_day), 'nullable', 'required_if:all_day,false', 'date_format:H:i', 'after:starts_at_time'],
            ],
            2 => [
                'title' => ['required', 'string', 'max:160'],
                'kind' => ['required', Rule::enum(EventKind::class)],
                'discipline_id' => ['nullable', 'integer', 'exists:disciplines,id'],
                'description' => ['nullable', 'string', 'max:2000'],
            ],
            3 => [
                'host_name' => ['required', 'string', 'min:3', 'max:160'],
                'province' => ['required', 'string', Rule::enum(Province::class)],
                'town' => ['nullable', 'string', 'max:120'],
            ],
            default => [],
        };
    }

    /**
     * Full-form rules used by {@see self::submit()}. Deliberately
     * merged from the per-step rules so there's exactly one source
     * for each rule and tests can't catch us with a review-only rule
     * that the step-by-step path never ran.
     *
     * @return array<string, array<int, string>>
     */
    private function fullRules(): array
    {
        return $this->rulesForStep(1)
            + $this->rulesForStep(2)
            + $this->rulesForStep(3);
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'title' => 'match title',
            'starts_on' => 'date',
            'starts_at_time' => 'start time',
            'ends_at_time' => 'end time',
            'host_name' => 'club, range or series',
            'province' => 'province',
            'discipline_id' => 'sport',
            'all_day' => 'all-day toggle',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'starts_on.required' => 'Enter the date.',
            'starts_on.after_or_equal' => 'The date must be today or later.',
            'starts_at_time.required_if' => 'Enter a start time.',
            'ends_at_time.required_if' => 'Enter an end time.',
            'ends_at_time.after' => 'The end time must be after the start time.',
            'title.required' => 'Enter the match title.',
            'host_name.required' => 'Enter the club, range or series.',
            'province.required' => 'Pick a province.',
        ];
    }

    /**
     * Highest step a request may land on: the first incomplete step,
     * or Review once Date, Match and Venue all pass.
     */
    private function reachableStep(int $requested): int
    {
        $requested = max(1, min(self::TOTAL_STEPS, $requested));

        $this->normalizeSubmissionFields();

        $reachable = 1;

        for ($step = 1; $step < self::TOTAL_STEPS; $step++) {
            if (! $this->stepDataIsValid($step)) {
                break;
            }

            $reachable = $step + 1;
        }

        return min($requested, $reachable);
    }

    private function stepDataIsValid(int $step): bool
    {
        $rules = $this->rulesForStep($step);

        if ($rules === []) {
            return true;
        }

        return Validator::make(
            $this->unwrapDataForValidation($this->getDataForValidation($rules)),
            $rules,
            $this->validationMessages(),
            $this->validationAttributes(),
        )->passes();
    }

    private function normalizeSubmissionFields(): void
    {
        if ($this->discipline_id === '') {
            $this->discipline_id = null;
        }

        $this->clearHiddenTimes();
    }

    /**
     * All-day matches have no start or end time. Leaving a rejected
     * pair in state blocks Next on fields the form no longer shows.
     */
    private function clearHiddenTimes(): void
    {
        if (! $this->all_day) {
            return;
        }

        $this->starts_at_time = '';
        $this->ends_at_time = '';
        $this->resetValidation(['starts_at_time', 'ends_at_time']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function computeTimestamps(): array
    {
        $date = Carbon::parse($this->starts_on);

        if ($this->all_day) {
            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        $start = $date->copy()->setTimeFromTimeString($this->starts_at_time ?: '09:00');
        $end = $date->copy()->setTimeFromTimeString($this->ends_at_time ?: '17:00');

        return [$start, $end];
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

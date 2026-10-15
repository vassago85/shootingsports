<?php

namespace App\Livewire;

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Enums\OrganisationUserRole;
use App\Mail\ClubApplicationReceivedMail;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * Shooter-facing membership application form embedded on
 * {@see resources/views/public/clubs/show.blade.php}. Reuses the
 * {@see ClubMember} status machine — applying creates (or revives) an
 * Applied record and emails every Admin / Secretary at the club.
 */
class ApplyForMembership extends Component
{
    public int $organisationId;

    public string $phone = '';

    public string $dateOfBirth = '';

    public string $category = ClubMemberCategory::Senior->value;

    public string $emergencyContactName = '';

    public string $emergencyContactPhone = '';

    public bool $consent = false;

    public bool $submitted = false;

    public function mount(Organisation $organisation): void
    {
        $this->organisationId = $organisation->id;
    }

    public function apply(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirect(route('login').'?redirect='.urlencode(url()->current()));

            return;
        }

        $organisation = $this->organisation();

        if (! $organisation->acceptsMembershipApplications()) {
            $this->addError('phone', 'This club is not accepting applications right now.');

            return;
        }

        $validated = $this->validate([
            'phone' => ['required', 'string', 'max:40'],
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'category' => ['required', 'string'],
            'emergencyContactName' => ['required', 'string', 'max:120'],
            'emergencyContactPhone' => ['required', 'string', 'max:40'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'You must agree to share your details with the club under POPIA.',
        ]);

        $category = ClubMemberCategory::tryFrom($validated['category']) ?? ClubMemberCategory::Senior;

        [$firstName, $lastName] = $this->splitName($user->name);

        $existing = ClubMember::query()
            ->where('organisation_id', $organisation->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->orWhere('email', strtolower((string) $user->email));
            })
            ->first();

        if ($existing !== null) {
            if (in_array($existing->status, [ClubMemberStatus::Applied, ClubMemberStatus::Active, ClubMemberStatus::Suspended], true)) {
                $this->submitted = true;

                return;
            }

            // Resigned / Rejected / Lapsed records are re-opened as a
            // fresh Applied entry so the history keeps the paper trail.
            $existing->forceFill([
                'user_id' => $user->id,
                'phone' => $validated['phone'],
                'date_of_birth' => $validated['dateOfBirth'] ?: null,
                'category' => $category,
                'emergency_contact_name' => $validated['emergencyContactName'],
                'emergency_contact_phone' => $validated['emergencyContactPhone'],
                'popia_consented_at' => now(),
            ])->save();
            $existing->transitionTo(ClubMemberStatus::Applied, $user, 'Re-applied');
            $member = $existing;
        } else {
            $member = ClubMember::create([
                'organisation_id' => $organisation->id,
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => strtolower((string) $user->email),
                'phone' => $validated['phone'],
                'date_of_birth' => $validated['dateOfBirth'] ?: null,
                'category' => $category,
                'status' => ClubMemberStatus::Applied,
                'emergency_contact_name' => $validated['emergencyContactName'],
                'emergency_contact_phone' => $validated['emergencyContactPhone'],
                'popia_consented_at' => now(),
            ]);
            $member->statusChanges()->create([
                'from_status' => null,
                'to_status' => ClubMemberStatus::Applied->value,
                'changed_by' => $user->id,
                'reason' => 'Applied online',
            ]);
        }

        $this->notifyClubAdmins($organisation, $member);
        $this->submitted = true;
    }

    public function render(): View
    {
        $user = auth()->user();
        $organisation = $this->organisation();

        return view('livewire.apply-for-membership', [
            'user' => $user,
            'organisation' => $organisation,
            'categories' => ClubMemberCategory::cases(),
            'existing' => $user instanceof User
                ? ClubMember::query()
                    ->where('organisation_id', $organisation->id)
                    ->where(function ($q) use ($user): void {
                        $q->where('user_id', $user->id)
                            ->orWhere('email', strtolower((string) $user->email));
                    })
                    ->first()
                : null,
        ]);
    }

    private function organisation(): Organisation
    {
        return Organisation::query()->findOrFail($this->organisationId);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        if ($parts === []) {
            return ['Shooter', 'Unknown'];
        }

        $first = array_shift($parts);
        $last = $parts === [] ? 'Unknown' : implode(' ', $parts);

        return [$first, $last];
    }

    private function notifyClubAdmins(Organisation $organisation, ClubMember $member): void
    {
        $recipients = $organisation->users()
            ->whereIn('organisation_user.role', array_map(
                fn (OrganisationUserRole $role): string => $role->value,
                OrganisationUserRole::canManageMembers(),
            ))
            ->whereNotNull('users.email')
            ->pluck('users.email')
            ->all();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->queue(new ClubApplicationReceivedMail($member));
        }
    }
}

<?php

namespace App\Livewire;

use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pins a published issue to the signed-in reader's library.
 * Guests are sent to sign in. A draft cannot be saved, and
 * taking an issue off the shelf does not delete the issue.
 */
class SaveMagazineIssue extends Component
{
    public int $issueId;

    public bool $saved = false;

    public function mount(MagazineIssue $issue): void
    {
        $this->issueId = $issue->id;
        $user = auth()->user();

        $this->saved = $user instanceof User
            && $issue->isPublished()
            && $user->savedMagazineIssues()->where('magazine_issues.id', $issue->id)->exists();
    }

    public function toggle(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirect(route('login'));

            return;
        }

        $issue = MagazineIssue::query()->find($this->issueId);

        if ($issue === null) {
            return;
        }

        $alreadySaved = $user->savedMagazineIssues()->where('magazine_issues.id', $issue->id)->exists();

        if ($alreadySaved) {
            $user->savedMagazineIssues()->detach($issue->id);
            $this->saved = false;

            return;
        }

        if (! $issue->isPublished()) {
            return;
        }

        $user->savedMagazineIssues()->syncWithoutDetaching([$issue->id]);
        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.save-magazine-issue');
    }
}

<?php

namespace App\Livewire;

use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The signed-in reader's shelf of magazine issues. Only issues
 * that are still published appear. Saving does not keep a private
 * copy: once an issue is taken down, it leaves this list.
 */
#[Layout('components.layouts.public', [
    'title' => 'My library',
    'description' => 'Magazine issues you saved, in one place.',
    'robots' => 'noindex, nofollow',
])]
class MagazineLibrary extends Component
{
    public function remove(int $issueId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirect(route('login'));

            return;
        }

        $user->savedMagazineIssues()->detach($issueId);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.magazine-library', [
            'issues' => $user instanceof User
                ? $this->issues($user)
                : Collection::make(),
        ]);
    }

    /**
     * @return Collection<int, MagazineIssue>
     */
    private function issues(User $user): Collection
    {
        return $user->savedMagazineIssues()
            ->published()
            ->with('author')
            ->orderBy('saved_magazine_issues.created_at', 'desc')
            ->get();
    }
}

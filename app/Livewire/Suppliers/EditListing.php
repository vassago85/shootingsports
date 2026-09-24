<?php

namespace App\Livewire\Suppliers;

use App\Models\Provider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Owner (or staff) update for the public-facing logo and copy.
 * Category, town, and review status stay with staff.
 */
class EditListing extends Component
{
    use WithFileUploads;

    public Provider $provider;

    #[Validate('nullable|image|mimes:jpeg,png,webp|max:3072')]
    public ?TemporaryUploadedFile $logo = null;

    #[Validate('nullable|string|max:160')]
    public string $tagline = '';

    #[Validate('required|string|min:20|max:2000')]
    public string $description = '';

    public function mount(Provider $provider): void
    {
        Gate::authorize('update', $provider);

        $this->provider = $provider;
        $this->tagline = (string) $provider->tagline;
        $this->description = (string) $provider->description;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->provider);

        $this->validate();

        $this->provider->tagline = filled($this->tagline) ? trim($this->tagline) : null;
        $this->provider->description = trim($this->description);

        if ($this->logo !== null && ! $this->provider->attachLogo($this->logo)) {
            $this->addError('logo', 'The logo could not be saved. Try a smaller PNG or JPEG.');

            return;
        }

        $this->provider->save();

        Session::flash('status', 'Your listing details are saved.');

        $this->redirect(route('suppliers.onboard.thanks', ['provider' => $this->provider->slug]), navigate: false);
    }

    public function render()
    {
        return view('livewire.suppliers.edit-listing')
            ->layout('components.layouts.public', ['title' => 'Your listing']);
    }
}

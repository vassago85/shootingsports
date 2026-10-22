<?php

use App\Livewire\MagazineLibrary;
use App\Livewire\SaveMagazineIssue;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
});

it('sends guests to sign in from the library and from the save control', function () {
    $issue = MagazineIssue::factory()->published()->create();

    $this->get(route('my-library'))
        ->assertRedirect(route('login'));

    Livewire::test(SaveMagazineIssue::class, ['issue' => $issue])
        ->call('toggle')
        ->assertRedirect(route('login'));

    expect($issue->savedByUsers()->count())->toBe(0);
});

it('saves a published issue to the signed-in reader library', function () {
    $user = User::factory()->create();
    $issue = MagazineIssue::factory()->published()->create([
        'title' => 'On Target Issue 87',
    ]);

    $this->actingAs($user)
        ->get(route('magazines.show', $issue->slug))
        ->assertOk()
        ->assertSee('Save to my library');

    Livewire::actingAs($user)
        ->test(SaveMagazineIssue::class, ['issue' => $issue])
        ->call('toggle')
        ->assertSet('saved', true);

    expect($user->savedMagazineIssues()->pluck('magazine_issues.id')->all())->toBe([$issue->id]);

    $this->actingAs($user)
        ->get(route('my-library'))
        ->assertOk()
        ->assertSee('On Target Issue 87')
        ->assertSee('Remove from my library')
        ->assertSee('noindex', false);

    $this->actingAs($user)
        ->get(route('me'))
        ->assertOk()
        ->assertSee('My library')
        ->assertSee('1 saved');
});

it('takes an issue off the library without deleting it', function () {
    $user = User::factory()->create();
    $issue = MagazineIssue::factory()->published()->create([
        'title' => 'On Target Issue 87',
    ]);
    $user->savedMagazineIssues()->attach($issue->id);

    Livewire::actingAs($user)
        ->test(MagazineLibrary::class)
        ->assertSee('On Target Issue 87')
        ->call('remove', $issue->id)
        ->assertDontSee('On Target Issue 87')
        ->assertSee('Nothing saved yet. Open a magazine and save it.');

    expect($user->savedMagazineIssues()->count())->toBe(0)
        ->and(MagazineIssue::query()->find($issue->id))->not->toBeNull();
});

it('does not show another reader their saves', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $issue = MagazineIssue::factory()->published()->create([
        'title' => 'Private Shelf Issue',
    ]);
    $owner->savedMagazineIssues()->attach($issue->id);

    $this->actingAs($other)
        ->get(route('my-library'))
        ->assertOk()
        ->assertDontSee('Private Shelf Issue')
        ->assertSee('Nothing saved yet. Open a magazine and save it.');

    Livewire::actingAs($other)
        ->test(MagazineLibrary::class)
        ->call('remove', $issue->id);

    expect($owner->savedMagazineIssues()->count())->toBe(1);
});

it('drops an issue from the library once it is taken down', function () {
    $user = User::factory()->create();
    $issue = MagazineIssue::factory()->published()->create([
        'title' => 'Withdrawn Issue',
    ]);
    $user->savedMagazineIssues()->attach($issue->id);

    $issue->update(['published_at' => null]);

    $this->actingAs($user)
        ->get(route('my-library'))
        ->assertOk()
        ->assertDontSee('Withdrawn Issue')
        ->assertSee('Nothing saved yet. Open a magazine and save it.');

    $this->actingAs($user)
        ->get(route('me'))
        ->assertOk()
        ->assertSee('Nothing saved yet');
});

it('refuses to save a draft', function () {
    $user = User::factory()->create();
    $issue = MagazineIssue::factory()->draft()->create();

    Livewire::actingAs($user)
        ->test(SaveMagazineIssue::class, ['issue' => $issue])
        ->call('toggle')
        ->assertSet('saved', false);

    expect($user->savedMagazineIssues()->count())->toBe(0);
});

it('hides the save control on a draft preview', function () {
    $issue = MagazineIssue::factory()->draft()->create([
        'title' => 'Private Pilot Issue',
    ]);

    $url = URL::temporarySignedRoute(
        'magazines.show',
        now()->addDays(14),
        ['issue' => $issue->slug]
    );

    $this->get($url)
        ->assertOk()
        ->assertDontSee('Save to my library');
});

it('lists the shelf with the most recently saved issue first', function () {
    $user = User::factory()->create();
    $older = MagazineIssue::factory()->published()->create(['title' => 'Older Issue']);
    $newer = MagazineIssue::factory()->published()->create(['title' => 'Newer Issue']);

    $user->savedMagazineIssues()->attach($older->id, [
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $user->savedMagazineIssues()->attach($newer->id, [
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('my-library'))
        ->assertOk()
        ->assertSeeInOrder(['Newer Issue', 'Older Issue']);
});

it('shows an empty library to a reader who has not saved anything', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('my-library'))
        ->assertOk()
        ->assertSee('Nothing saved yet. Open a magazine and save it.')
        ->assertSee('Browse magazines');

    $this->actingAs($user)
        ->get(route('me'))
        ->assertOk()
        ->assertSee('My library')
        ->assertSee('Nothing saved yet');
});

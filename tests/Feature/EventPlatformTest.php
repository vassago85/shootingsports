<?php

use App\Enums\EntryCollection;
use App\Enums\EntryPaymentStatus;
use App\Enums\EntryStatus;
use App\Enums\EventKind;
use App\Enums\EventStatus;
use App\Livewire\EnterEvent;
use App\Livewire\FindYourDiscipline;
use App\Models\Article;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\EventResult;
use App\Models\User;
use App\Support\DisciplineRecommender;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('filters training events and redirects old match urls', function () {
    $training = Event::factory()->confirmed()->create([
        'title' => 'PRS Fundamentals',
        'kind' => EventKind::Training,
    ]);
    $competition = Event::factory()->confirmed()->create([
        'title' => 'Club PRS',
        'kind' => EventKind::Competition,
    ]);

    $this->get(route('calendar', ['kind' => 'training']))
        ->assertOk()
        ->assertSee('PRS Fundamentals')
        ->assertDontSee('Club PRS');

    $this->get('/matches/'.$competition->slug)
        ->assertRedirect(route('events.show', $competition->slug));

    $this->get(route('events.show', $training))->assertOk()->assertSee('PRS Fundamentals');
});

it('recommends pistol speed sports', function () {
    $slugs = (new DisciplineRecommender)->recommend([
        'firearm' => 'pistol',
        'pace' => 'speed',
        'setting' => 'outdoor',
        'distance' => 'up-close',
        'company' => 'individual',
        'budget' => 'medium',
    ]);

    expect($slugs)->toContain('ipsc-practical')
        ->and($slugs)->toContain('steel-challenge')
        ->and($slugs[0])->toBe('ipsc-practical');
});

it('walks the find your discipline questions', function () {
    Discipline::factory()->create([
        'slug' => 'ipsc-practical',
        'name' => 'IPSC',
        'short_blurb' => 'Practical pistol.',
        'is_published' => true,
    ]);

    Livewire::test(FindYourDiscipline::class)
        ->call('choose', 'firearm', 'pistol')
        ->call('choose', 'pace', 'speed')
        ->call('choose', 'setting', 'outdoor')
        ->call('choose', 'distance', 'up-close')
        ->call('choose', 'company', 'individual')
        ->call('choose', 'budget', 'medium')
        ->assertSee('IPSC');
});

it('records an entry, blocks a full event, and allows withdrawal', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::EntriesOpen,
        'accepts_platform_entries' => true,
        'entry_collection' => EntryCollection::External,
        'capacity' => 1,
    ]);
    $first = User::factory()->create();
    $second = User::factory()->create();

    Livewire::actingAs($first)->test(EnterEvent::class, ['event' => $event])
        ->call('enter')
        ->assertHasNoErrors();

    expect($event->fresh()->enteredCount())->toBe(1);

    Livewire::actingAs($second)->test(EnterEvent::class, ['event' => $event->fresh()])
        ->call('enter')
        ->assertHasErrors('division');

    Livewire::actingAs($first)->test(EnterEvent::class, ['event' => $event->fresh()])
        ->call('withdraw');

    expect($event->entries()->where('status', EntryStatus::Entered)->count())->toBe(0);
});

it('starts a paystack charge when the event takes payment', function () {
    config(['services.paystack.secret_key' => 'sk_test_dummy']);

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/test',
                'access_code' => 'abc',
                'reference' => 'ignored',
            ],
        ]),
    ]);

    $event = Event::factory()->create([
        'status' => EventStatus::EntriesOpen,
        'accepts_platform_entries' => true,
        'entry_collection' => EntryCollection::Paystack,
        'entry_fee_cents' => 35000,
    ]);
    $shooter = User::factory()->create();

    Livewire::actingAs($shooter)->test(EnterEvent::class, ['event' => $event])
        ->call('enter')
        ->assertRedirect('https://checkout.paystack.com/test');

    $entry = $event->entries()->first();

    expect($entry->status)->toBe(EntryStatus::PendingPayment)
        ->and($entry->payment_status)->toBe(EntryPaymentStatus::Pending)
        ->and($entry->amount_cents)->toBe(35000);
});

it('ranks linked results by wins then podiums then starts', function () {
    $discipline = Discipline::factory()->create(['is_published' => true, 'name' => 'Steel', 'slug' => 'steel-challenge']);
    $winner = User::factory()->create(['name' => 'Ada Winner']);
    $podium = User::factory()->create(['name' => 'Bea Podium']);
    $event = Event::factory()->confirmed()->create(['starts_at' => now()]);

    EventResult::query()->create([
        'event_id' => $event->id,
        'user_id' => $podium->id,
        'display_name' => $podium->name,
        'discipline_id' => $discipline->id,
        'placing' => 2,
    ]);
    EventResult::query()->create([
        'event_id' => $event->id,
        'user_id' => $winner->id,
        'display_name' => $winner->name,
        'discipline_id' => $discipline->id,
        'placing' => 1,
    ]);
    EventResult::query()->create([
        'event_id' => $event->id,
        'user_id' => null,
        'display_name' => 'Unlinked',
        'discipline_id' => $discipline->id,
        'placing' => 1,
    ]);

    $this->get(route('disciplines.rankings', $discipline))
        ->assertOk()
        ->assertSee('not a SAPRF')
        ->assertSeeInOrder(['Ada Winner', 'Bea Podium'])
        ->assertDontSee('Unlinked');
});

it('shows published articles on the feed and hides drafts', function () {
    $partner = User::factory()->create(['is_media_partner' => true, 'name' => 'On Target']);
    Article::query()->create([
        'user_id' => $partner->id,
        'title' => 'Loch Lynne report',
        'excerpt' => 'A Saturday on steel.',
        'body' => 'The squad finished in the wind.',
        'event_kinds' => [EventKind::Competition->value],
        'published_at' => now(),
    ]);
    Article::query()->create([
        'user_id' => $partner->id,
        'title' => 'Secret draft',
        'body' => 'Not ready.',
        'published_at' => null,
    ]);

    $this->get(route('feed'))
        ->assertOk()
        ->assertSee('Loch Lynne report')
        ->assertDontSee('Secret draft');

    $this->get(route('feed', ['kind' => 'training']))
        ->assertOk()
        ->assertDontSee('Loch Lynne report');

    $this->actingAs($partner)->get('/desk')->assertOk();
});

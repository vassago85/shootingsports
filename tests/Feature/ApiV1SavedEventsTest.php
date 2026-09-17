<?php

use App\Models\Event;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('lists the shooter saved events via the API', function (): void {
    $mine = Event::factory()->confirmed()->create(['title' => 'My Steel Sunday']);
    $other = Event::factory()->confirmed()->create(['title' => 'Someone Else Open']);

    $this->user->savedEvents()->attach($mine->id);

    $titles = collect(
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/me/saved-events')
            ->assertOk()
            ->json('data')
    )->pluck('title');

    expect($titles)->toContain('My Steel Sunday')->not->toContain('Someone Else Open');
});

it('lets the shooter save an event via POST', function (): void {
    $event = Event::factory()->confirmed()->create();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/me/saved-events', ['event_id' => $event->id])
        ->assertCreated()
        ->assertJson(['event_id' => $event->id, 'saved' => true]);

    expect($this->user->savedEvents()->pluck('events.id'))->toContain($event->id);
});

it('is idempotent when saving the same event twice', function (): void {
    $event = Event::factory()->confirmed()->create();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/me/saved-events', ['event_id' => $event->id])
        ->assertCreated();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/me/saved-events', ['event_id' => $event->id])
        ->assertCreated();

    expect($this->user->savedEvents()->count())->toBe(1);
});

it('lets the shooter unsave an event via DELETE', function (): void {
    $event = Event::factory()->confirmed()->create();
    $this->user->savedEvents()->attach($event->id);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v1/me/saved-events/'.$event->id)
        ->assertOk()
        ->assertJson(['saved' => false]);

    expect($this->user->savedEvents()->count())->toBe(0);
});

it('rejects saved-events access without a token', function (): void {
    $this->getJson('/api/v1/me/saved-events')->assertStatus(401);
    $this->postJson('/api/v1/me/saved-events', ['event_id' => 1])->assertStatus(401);
});

<?php

use App\Enums\OrganisationType;
use App\Models\Discipline;
use App\Models\Follow;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

function authHeader(): array
{
    return ['Authorization' => 'Bearer '.test()->token];
}

it('lists only club and discipline follows (not venues)', function (): void {
    $club = Organisation::factory()->create();
    $discipline = Discipline::factory()->create();

    Follow::create([
        'user_id' => $this->user->id,
        'followable_type' => 'organisation',
        'followable_id' => $club->id,
    ]);
    Follow::create([
        'user_id' => $this->user->id,
        'followable_type' => 'discipline',
        'followable_id' => $discipline->id,
    ]);
    // Venue follow from the web — must not appear in the API list.
    $this->user->follows()->create([
        'followable_type' => 'venue',
        'followable_id' => 1,
    ]);

    $data = $this->withHeaders(authHeader())
        ->getJson('/api/v1/me/follows')
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(2);

    $types = collect($data)->pluck('type')->all();
    expect($types)->toContain('club')->toContain('discipline');
});

it('creates a club follow via POST /me/follows', function (): void {
    $club = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'name' => 'Test Sports Club',
    ]);

    $this->withHeaders(authHeader())
        ->postJson('/api/v1/me/follows', ['type' => 'club', 'id' => $club->id])
        ->assertCreated()
        ->assertJsonPath('data.type', 'club')
        ->assertJsonPath('data.followable_id', $club->id)
        ->assertJsonPath('data.name', 'Test Sports Club');

    expect(Follow::where('user_id', $this->user->id)->count())->toBe(1);
});

it('accepts the organisation alias on input and normalises to club on output', function (): void {
    $club = Organisation::factory()->create();

    $this->withHeaders(authHeader())
        ->postJson('/api/v1/me/follows', ['type' => 'organisation', 'id' => $club->id])
        ->assertCreated()
        ->assertJsonPath('data.type', 'club');
});

it('creates a discipline follow', function (): void {
    $discipline = Discipline::factory()->create(['name' => 'Precision Rifle']);

    $this->withHeaders(authHeader())
        ->postJson('/api/v1/me/follows', ['type' => 'discipline', 'id' => $discipline->id])
        ->assertCreated()
        ->assertJsonPath('data.type', 'discipline')
        ->assertJsonPath('data.name', 'Precision Rifle');
});

it('is idempotent — second POST returns 200 with the same follow', function (): void {
    $club = Organisation::factory()->create();

    $this->withHeaders(authHeader())
        ->postJson('/api/v1/me/follows', ['type' => 'club', 'id' => $club->id])
        ->assertCreated();

    $this->withHeaders(authHeader())
        ->postJson('/api/v1/me/follows', ['type' => 'club', 'id' => $club->id])
        ->assertOk();

    expect(Follow::where('user_id', $this->user->id)->count())->toBe(1);
});

it('rejects an unknown type with 422', function (): void {
    $this->withHeaders(authHeader())
        ->postJson('/api/v1/me/follows', ['type' => 'shop', 'id' => 1])
        ->assertStatus(422);
});

it('rejects a club id that does not exist with 404', function (): void {
    $this->withHeaders(authHeader())
        ->postJson('/api/v1/me/follows', ['type' => 'club', 'id' => 999999])
        ->assertNotFound();
});

it('deletes a follow via DELETE /me/follows/{type}/{id}', function (): void {
    $club = Organisation::factory()->create();
    Follow::create([
        'user_id' => $this->user->id,
        'followable_type' => 'organisation',
        'followable_id' => $club->id,
    ]);

    $this->withHeaders(authHeader())
        ->deleteJson('/api/v1/me/follows/club/'.$club->id)
        ->assertStatus(204);

    expect(Follow::where('user_id', $this->user->id)->count())->toBe(0);
});

it('DELETE is idempotent — 204 when the follow does not exist', function (): void {
    $club = Organisation::factory()->create();

    $this->withHeaders(authHeader())
        ->deleteJson('/api/v1/me/follows/club/'.$club->id)
        ->assertStatus(204);
});

it('tags organisations as national when they are a federation', function (): void {
    $federation = Organisation::factory()->create([
        'type' => OrganisationType::Federation,
    ]);

    $this->withHeaders(authHeader())
        ->postJson('/api/v1/me/follows', ['type' => 'club', 'id' => $federation->id])
        ->assertCreated()
        ->assertJsonPath('data.group', 'national');
});

it('requires authentication for every follows endpoint', function (): void {
    $this->getJson('/api/v1/me/follows')->assertStatus(401);
    $this->postJson('/api/v1/me/follows', [])->assertStatus(401);
    $this->deleteJson('/api/v1/me/follows/club/1')->assertStatus(401);
});

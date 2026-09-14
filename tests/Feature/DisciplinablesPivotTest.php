<?php

use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use Illuminate\Database\QueryException;

it('attaches and detaches disciplines on an organisation', function () {
    $organisation = Organisation::factory()->create();
    $prs = Discipline::factory()->create(['slug' => 'prs-test', 'name' => 'PRS']);
    $pr22 = Discipline::factory()->create(['slug' => 'pr22-test', 'name' => 'PR22']);

    $organisation->attachDiscipline($prs, primary: true);
    $organisation->attachDiscipline($pr22);

    expect($organisation->disciplines()->count())->toBe(2)
        ->and($organisation->primaryDiscipline()?->is($prs))->toBeTrue();

    $organisation->detachDiscipline($pr22);

    expect($organisation->disciplines()->count())->toBe(1);
});

it('syncs a primary discipline on an event', function () {
    $event = Event::factory()->create();
    $a = Discipline::factory()->create();
    $b = Discipline::factory()->create();

    $event->syncDisciplines([$a->id, $b->id], $b->id);

    expect($event->primaryDiscipline()?->is($b))->toBeTrue()
        ->and($event->disciplines()->pluck('disciplines.id'))->toContain($a->id, $b->id);
});

it('rejects a duplicate disciplinable pivot row', function () {
    $organisation = Organisation::factory()->create();
    $discipline = Discipline::factory()->create();

    $organisation->attachDiscipline($discipline);

    expect(fn () => $organisation->disciplines()->attach($discipline->id))
        ->toThrow(QueryException::class);
});

<?php

use App\Models\Discipline;
use App\Models\Event;
use App\Models\EventEntry;
use App\Models\EventResult;
use App\Models\User;
use App\Services\ImpactScoring\ImpactResultsImporter;
use App\Services\ImpactScoring\ImpactScoringClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

it('reads an Impact match id from the match url', function () {
    $client = app(ImpactScoringClient::class);

    expect($client->parseMatchId('https://www.impactscoring.net/match/results?id=11086'))->toBe(11086)
        ->and($client->parseMatchId('https://www.impactscoring.net/match/view?id=10558'))->toBe(10558)
        ->and($client->parseMatchId('https://www.impactscoring.net/match/results/11086'))->toBe(11086)
        ->and($client->parseMatchId('11086'))->toBe(11086);
});

it('rejects a link that is not the Impact match page', function () {
    expect(fn () => app(ImpactScoringClient::class)->parseMatchId('https://example.com/match/view?id=10558'))
        ->toThrow(RuntimeException::class);
});

it('imports placing, division and score from an Impact match url', function () {
    Http::fake([
        'https://app-api.impactscoring.net/rest/website/getLiveScores*' => Http::response([
            'errorCode' => 0,
            'payload' => [
                'match' => ['id' => 11086, 'name' => 'Club Night'],
                'shooters' => [
                    ['id' => 1, 'name' => 'Ada Shooter', 'rank' => 1, 'points' => 8700, 'divisionName' => 'Open'],
                    ['id' => 2, 'name' => 'Bo Guest', 'rank' => 2, 'points' => 7450, 'divisionName' => 'Open'],
                ],
            ],
        ]),
    ]);

    $event = Event::factory()->create();
    $discipline = Discipline::factory()->create();
    $event->syncDisciplines([$discipline->id], $discipline->id);
    $shooter = User::factory()->create(['name' => 'Ada Shooter']);
    EventEntry::factory()->create([
        'event_id' => $event->id,
        'user_id' => $shooter->id,
    ]);
    EventResult::factory()->create([
        'event_id' => $event->id,
        'discipline_id' => $discipline->id,
        'display_name' => 'Old Result',
    ]);

    $imported = app(ImpactResultsImporter::class)->import(
        $event,
        'https://www.impactscoring.net/match/view?id=11086',
    );

    expect($imported['name'])->toBe('Club Night')
        ->and($imported['count'])->toBe(2)
        ->and($event->results()->pluck('display_name')->all())->not->toContain('Old Result');

    $winner = EventResult::query()->where('event_id', $event->id)->where('display_name', 'Ada Shooter')->first();

    expect($winner)->not->toBeNull()
        ->and($winner->user_id)->toBe($shooter->id)
        ->and($winner->placing)->toBe(1)
        ->and($winner->division)->toBe('Open')
        ->and($winner->score)->toBe('87')
        ->and($winner->field_size)->toBe(2)
        ->and($winner->discipline_id)->toBe($discipline->id);

    expect(EventResult::query()->where('event_id', $event->id)->where('display_name', 'Bo Guest')->value('score'))
        ->toBe('74.5');
});

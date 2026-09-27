<?php

namespace App\Support;

/**
 * Maps six plain answers onto existing discipline slugs.
 * The lists are explicit so a new sport is a code change, not a guess.
 */
final class DisciplineRecommender
{
    /**
     * @param  array{firearm: string, pace: string, setting: string, distance: string, company: string, budget: string}  $answers
     * @return list<string>
     */
    public function recommend(array $answers): array
    {
        $scores = [];

        $add = function (string $slug, int $points) use (&$scores): void {
            $scores[$slug] = ($scores[$slug] ?? 0) + $points;
        };

        $firearm = $answers['firearm'];
        $pace = $answers['pace'];
        $setting = $answers['setting'];
        $distance = $answers['distance'];
        $company = $answers['company'];
        $budget = $answers['budget'];

        if ($firearm === 'none' || $budget === 'low') {
            $add('training', 4);
        }

        if ($firearm === 'pistol' || $firearm === 'none') {
            if ($pace === 'speed') {
                $add('ipsc-practical', 5);
                $add('steel-challenge', 4);
                $add('idpa', 3);
            } else {
                $add('sport-pistol', 5);
                $add('target-pistol', 4);
            }
        }

        if ($firearm === 'rifle' || $firearm === 'none') {
            if ($pace === 'precision') {
                $add('precision-rifle', 5);
                $add('prs', 4);
                if ($distance === 'past-300') {
                    $add('elr', $budget === 'low' ? 1 : 4);
                    $add('f-class', 3);
                } else {
                    $add('pr22-rimfire', $budget === 'high' ? 2 : 5);
                    $add('nrl-hunter', 3);
                }
            } else {
                $add('ipsc-rifle', 4);
                $add('3-gun', 4);
                $add('xtreme-steel', 3);
            }
        }

        if ($firearm === 'shotgun') {
            $add('sporting-clays', 5);
            $add('trap', 4);
            $add('skeet', 3);
            $add('down-the-line', 3);
        }

        if ($setting === 'indoor') {
            $add('10m-air-rifle', 4);
            $add('target-pistol', 2);
        }

        if ($company === 'together') {
            $add('gong-shooting', 3);
            $add('3-gun', 1);
        }

        if ($budget === 'low') {
            $add('pr22-rimfire', 2);
            $add('steel-challenge', 2);
            $add('elr', -3);
        }

        arsort($scores);

        return collect($scores)
            ->filter(fn (int $score): bool => $score > 0)
            ->keys()
            ->take(4)
            ->values()
            ->all();
    }
}

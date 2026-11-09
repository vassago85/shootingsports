<?php

namespace App\Support;

use App\Models\Discipline;
use Illuminate\Support\Collection;

/**
 * The public event-type glossary. Every published discipline, from the database.
 */
final class Glossary
{
    /**
     * @return Collection<int, Discipline>
     */
    public static function disciplines(): Collection
    {
        return Discipline::query()
            ->where('is_published', true)
            ->excludingVerificationFixtures()
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Discipline>  $disciplines
     * @return array<string, mixed>
     */
    public static function jsonLd(Collection $disciplines): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'DefinedTermSet',
            'name' => 'Shooting event types',
            'url' => route('glossary'),
            'hasDefinedTerm' => $disciplines->map(fn (Discipline $discipline): array => array_filter([
                '@type' => 'DefinedTerm',
                'name' => $discipline->name,
                'termCode' => $discipline->slug,
                'description' => $discipline->glossaryDescription(),
                'url' => route('glossary').'#'.$discipline->slug,
            ]))->values()->all(),
        ];
    }
}

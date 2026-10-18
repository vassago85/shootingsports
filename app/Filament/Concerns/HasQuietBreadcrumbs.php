<?php

namespace App\Filament\Concerns;

use Filament\Resources\Pages\ListRecords;

/**
 * Suppress the "<Plural label> › List" breadcrumb trail Filament
 * emits by default on resource list pages — it tells staff nothing
 * they do not already read in the page title. Create and Edit pages
 * still show their breadcrumbs because those _do_ carry information
 * ("Clubs › Create" / "Clubs › Edit <name>").
 *
 * @mixin ListRecords
 */
trait HasQuietBreadcrumbs
{
    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }
}

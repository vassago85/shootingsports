<?php

namespace App\Console\Commands;

use App\Support\AnswerPages;
use App\Support\PublicCache;
use Illuminate\Console\Command;

class RefreshAnswerPages extends Command
{
    protected $signature = 'answers:refresh';

    protected $description = 'Stamp answer-page freshness and drop feed caches. Weekend URLs are not pinged to IndexNow (they canonicalise to /calendar).';

    public function handle(): int
    {
        AnswerPages::stamp();
        PublicCache::forgetSitemaps();

        $this->info('Refreshed answer-page freshness and dropped sitemap caches.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Support\AnswerPages;
use App\Support\IndexNow;
use App\Support\PublicCache;
use Illuminate\Console\Command;

class RefreshAnswerPages extends Command
{
    protected $signature = 'answers:refresh';

    protected $description = 'Stamp answer-page freshness, drop feed caches, and ping IndexNow.';

    public function handle(): int
    {
        AnswerPages::stamp();
        PublicCache::forgetSitemaps();

        $urls = AnswerPages::publicUrls();

        foreach ($urls as $url) {
            IndexNow::ping($url);
        }

        $this->info('Refreshed '.count($urls).' answer pages.');

        return self::SUCCESS;
    }
}

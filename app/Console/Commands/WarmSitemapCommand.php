<?php

namespace App\Console\Commands;

use App\Support\PublicCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

#[Signature('sitemap:warm')]
#[Description('Regenerate the cached public sitemap')]
class WarmSitemapCommand extends Command
{
    public function handle(Kernel $kernel): int
    {
        PublicCache::forgetSitemaps();

        $paths = [
            '/sitemap.xml',
            '/sitemaps/pages.xml',
            '/sitemaps/events.xml',
            '/sitemaps/organisations.xml',
            '/sitemaps/venues.xml',
            '/sitemaps/disciplines.xml',
            '/sitemaps/providers.xml',
        ];

        $index = $this->fetch($kernel, '/sitemap.xml');

        if ($index === null) {
            return self::FAILURE;
        }

        preg_match_all('#<loc>([^<]+)</loc>#', $index, $matches);

        foreach ($matches[1] ?? [] as $loc) {
            $path = parse_url($loc, PHP_URL_PATH);

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        foreach (array_unique($paths) as $path) {
            if ($path === '/sitemap.xml') {
                continue;
            }

            if ($this->fetch($kernel, $path) === null) {
                return self::FAILURE;
            }
        }

        $this->info('Sitemap cache warmed.');

        return self::SUCCESS;
    }

    private function fetch(Kernel $kernel, string $path): ?string
    {
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        if ($response->getStatusCode() !== 200) {
            $this->error($path.' returned '.$response->getStatusCode());

            return null;
        }

        return (string) $response->getContent();
    }
}

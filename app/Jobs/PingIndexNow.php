<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells IndexNow (Bing and the engines that feed AI search) that one
 * public URL changed. Failures are logged and swallowed so a slow or
 * down endpoint never fails the queue or the save that dispatched this.
 */
class PingIndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $url) {}

    public function handle(): void
    {
        $key = (string) config('services.indexnow.key');
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($key === '' || ! is_string($host) || $host === '') {
            return;
        }

        $keyLocation = rtrim((string) config('app.url'), '/').'/'.$key.'.txt';

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->post('https://api.indexnow.org/indexnow', [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => $keyLocation,
                    'urlList' => [$this->url],
                ]);

            if ($response->failed()) {
                Log::warning('IndexNow ping failed', [
                    'url' => $this->url,
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('IndexNow ping failed', [
                'url' => $this->url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

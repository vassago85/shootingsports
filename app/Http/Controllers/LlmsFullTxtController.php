<?php

namespace App\Http\Controllers;

use App\Support\AnswerPages;
use App\Support\EventFeed;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class LlmsFullTxtController extends Controller
{
    public function __invoke(): Response
    {
        $body = Cache::remember(
            'feeds.llms-full.'.AnswerPages::lastmod(),
            now()->addDay(),
            fn (): string => EventFeed::plainText(),
        );

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

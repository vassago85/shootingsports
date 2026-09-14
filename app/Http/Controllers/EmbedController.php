<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use App\Support\EmbedTheme;
use App\Support\EmbedUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EmbedController extends Controller
{
    public function calendar(Request $request): View
    {
        $events = PublicEventQuery::fromRequest($request)->get();
        $theme = EmbedTheme::fromRequest($request);

        $embed = EmbedUrl::fromString($request->fullUrl());

        return view('public.embed.calendar', [
            'events' => $events,
            'theme' => $theme,
            'listingName' => $this->listingName($request),
            'discipline' => $request->string('discipline')->toString() ?: null,
            'oembedUrl' => route('oembed', ['url' => $embed?->calendarUrl ?? url('/embed/calendar')]),
        ]);
    }

    public function oembed(Request $request): JsonResponse
    {
        $embed = EmbedUrl::fromString($request->string('url')->toString());

        abort_unless($embed instanceof EmbedUrl, 404);

        $width = max(320, min(1200, $request->integer('maxwidth') ?: 800));
        $height = $request->integer('maxheight') ?: $embed->height;
        $height = max(320, min(1600, $height));

        $title = $this->listingName(Request::create($embed->calendarUrl, 'GET', $embed->query))
            ?: 'Match calendar';

        $html = sprintf(
            '<iframe src="%s" width="%d" height="%d" frameborder="0" scrolling="no" title="%s"></iframe>',
            e($embed->calendarUrl),
            $width,
            $height,
            e($title.' · Shooting Sports'),
        );

        return response()->json([
            'version' => '1.0',
            'type' => 'rich',
            'provider_name' => 'Shooting Sports',
            'provider_url' => url('/'),
            'title' => $title,
            'width' => $width,
            'height' => $height,
            'html' => $html,
        ]);
    }

    public function script(): Response
    {
        $js = <<<'JS'
(function () {
  var script = document.currentScript;
  if (!script) return;
  var iframe = document.createElement("iframe");
  var base = script.src.replace(/\.js(?:\?.*)?$/, "");
  var keys = ["club", "organisation", "venue", "discipline", "province", "accent", "color", "bg", "ink", "font", "theme"];
  var params = [];
  keys.forEach(function (key) {
    var value = script.getAttribute("data-" + key);
    if (value) params.push(key + "=" + encodeURIComponent(value));
  });
  iframe.src = base + (params.length ? "?" + params.join("&") : "");
  iframe.title = "Shooting Sports match calendar";
  var height = parseInt(script.getAttribute("data-height") || "520", 10);
  if (isNaN(height) || height < 320) height = 520;
  if (height > 1600) height = 1600;
  iframe.style.cssText = "width:100%;min-height:" + height + "px;border:0;background:transparent;";
  iframe.loading = "lazy";
  script.parentNode.insertBefore(iframe, script);
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function listingName(Request $request): ?string
    {
        $orgSlug = $request->string('organisation')->toString()
            ?: $request->string('club')->toString();

        if ($orgSlug !== '') {
            return Organisation::query()->published()->where('slug', $orgSlug)->value('name');
        }

        $venueSlug = $request->string('venue')->toString();

        if ($venueSlug !== '') {
            return Venue::query()->published()->where('slug', $venueSlug)->value('name');
        }

        return null;
    }
}

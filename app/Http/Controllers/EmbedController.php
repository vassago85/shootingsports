<?php

namespace App\Http\Controllers;

use App\Queries\PublicEventQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EmbedController extends Controller
{
    public function calendar(Request $request): View
    {
        $events = PublicEventQuery::fromRequest($request)->get();

        return view('public.embed.calendar', [
            'events' => $events,
            'discipline' => $request->string('discipline')->toString() ?: null,
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
  var params = [];
  var discipline = script.getAttribute("data-discipline");
  var province = script.getAttribute("data-province");
  if (discipline) params.push("discipline=" + encodeURIComponent(discipline));
  if (province) params.push("province=" + encodeURIComponent(province));
  iframe.src = base + (params.length ? "?" + params.join("&") : "");
  iframe.title = "Shooting Sports match calendar";
  iframe.style.cssText = "width:100%;min-height:520px;border:1px solid #d3d6d0;background:#f0f0ec;";
  iframe.loading = "lazy";
  script.parentNode.insertBefore(iframe, script);
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

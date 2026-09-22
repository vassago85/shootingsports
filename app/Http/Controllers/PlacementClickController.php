<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use Illuminate\Http\RedirectResponse;

class PlacementClickController extends Controller
{
    public function __invoke(Placement $placement): RedirectResponse
    {
        abort_unless($placement->is_active, 404);

        $placement->loadMissing('provider');

        $url = $placement->destinationUrl();

        abort_unless(is_string($url) && preg_match('#\Ahttps?://#i', $url) === 1, 404);

        $placement->increment('clicks');

        return redirect()->away($url);
    }
}

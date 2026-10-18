<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\View;

class AgencyAgreement
{
    public static function currentVersion(): string
    {
        return (string) config('platform.agency_agreement_version', '2026-10-18');
    }

    public static function body(): string
    {
        return View::make('agreements.collection-agent-agreement', [
            'version' => self::currentVersion(),
        ])->render();
    }
}

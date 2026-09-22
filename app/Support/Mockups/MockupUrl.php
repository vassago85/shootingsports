<?php

namespace App\Support\Mockups;

class MockupUrl
{
    /**
     * Mockup route URL. Keeps the mobile review frame when the current
     * request asked for it, unless the caller passes preserveDevice: false.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function to(string $name, array $parameters = [], bool $preserveDevice = true): string
    {
        if ($preserveDevice && request()->query('device') === 'mobile' && ! array_key_exists('device', $parameters)) {
            $parameters['device'] = 'mobile';
        }

        if (array_key_exists('device', $parameters) && ($parameters['device'] === null || $parameters['device'] === '')) {
            unset($parameters['device']);
        }

        return route($name, $parameters);
    }
}

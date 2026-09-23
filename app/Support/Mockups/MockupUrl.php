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

        $theme = request()->query('theme');

        if ($preserveDevice && in_array($theme, ['light', 'dark'], true) && ! array_key_exists('theme', $parameters)) {
            $parameters['theme'] = $theme;
        }

        if (array_key_exists('theme', $parameters) && ! in_array($parameters['theme'], ['light', 'dark'], true)) {
            unset($parameters['theme']);
        }

        return route($name, $parameters);
    }
}

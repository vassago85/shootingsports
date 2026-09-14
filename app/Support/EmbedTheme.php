<?php

namespace App\Support;

use Illuminate\Http\Request;

class EmbedTheme
{
    /**
     * @var array<string, array{family: string, href: ?string}>
     */
    public const FONTS = [
        'ibm-plex' => [
            'family' => '"IBM Plex Sans", system-ui, sans-serif',
            'href' => 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500&family=IBM+Plex+Mono:wght@400;500&display=swap',
        ],
        'inter' => [
            'family' => 'Inter, system-ui, sans-serif',
            'href' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap',
        ],
        'source-sans' => [
            'family' => '"Source Sans 3", system-ui, sans-serif',
            'href' => 'https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600&display=swap',
        ],
        'roboto' => [
            'family' => 'Roboto, system-ui, sans-serif',
            'href' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
        ],
        'open-sans' => [
            'family' => '"Open Sans", system-ui, sans-serif',
            'href' => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600&display=swap',
        ],
        'saira' => [
            'family' => '"Saira Condensed", "Arial Narrow", sans-serif',
            'href' => 'https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@600&display=swap',
        ],
        'system' => [
            'family' => 'system-ui, -apple-system, "Segoe UI", sans-serif',
            'href' => null,
        ],
    ];

    public function __construct(
        public ?string $accent = null,
        public ?string $background = null,
        public ?string $ink = null,
        public string $font = 'ibm-plex',
        public ?string $theme = null,
        public int $height = 520,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $font = strtolower($request->string('font')->toString());
        $theme = strtolower($request->string('theme')->toString());
        $height = $request->integer('height') ?: 520;

        return new self(
            accent: self::hex($request->input('accent') ?? $request->input('color')),
            background: self::hex($request->input('bg')),
            ink: self::hex($request->input('ink')),
            font: array_key_exists($font, self::FONTS) ? $font : 'ibm-plex',
            theme: in_array($theme, ['light', 'dark'], true) ? $theme : null,
            height: max(320, min(1600, $height)),
        );
    }

    public function cssVariables(): string
    {
        $rules = [];

        if ($this->accent) {
            $rules[] = '--brass: '.$this->accent;
            $rules[] = '--brass-dk: '.$this->accent;
            $rules[] = '--brass-lt: '.$this->accent;
        }

        if ($this->background) {
            $rules[] = '--base: '.$this->background;
            $rules[] = '--surface: '.$this->background;
        }

        if ($this->ink) {
            $rules[] = '--ink: '.$this->ink;
        }

        $rules[] = '--f-body: '.$this->fontFamily();

        if ($this->font !== 'ibm-plex') {
            $rules[] = '--f-display: '.$this->fontFamily();
        }

        return implode('; ', $rules);
    }

    public function fontFamily(): string
    {
        return self::FONTS[$this->font]['family'];
    }

    public function googleFontsHref(): ?string
    {
        return self::FONTS[$this->font]['href'];
    }

    public static function hex(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = ltrim(trim($value), '#');

        if (! preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
            return null;
        }

        return '#'.$value;
    }
}

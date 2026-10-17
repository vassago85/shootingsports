<?php

namespace App\Rules;

use App\Enums\Province;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Catches rows like "Taai Boskraal" with town = "Western Cape". Town
 * must be an actual town or city, never the name or value of a
 * Province enum case. Matches case-insensitively against both the
 * label ("Western Cape") and the enum value ("western_cape").
 */
class NotAProvinceName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $normalised = mb_strtolower(trim($value));

        foreach (Province::cases() as $province) {
            if ($normalised === mb_strtolower($province->getLabel())
                || $normalised === $province->value
                || $normalised === str_replace('_', ' ', $province->value)) {
                $fail('Enter the town or city, not the province name.');

                return;
            }
        }
    }
}

<?php

namespace App\Support;

use App\Models\ClubMember;
use App\Models\Organisation;

/**
 * Helpers for the club-configurable "custom fields" on
 * membership records. The schema lives on
 * {@see Organisation::$membership_custom_fields} as a list of
 * `[key, label, type, required, options?]` entries and the captured
 * values live on {@see ClubMember::$custom_attributes}.
 *
 * Keeping the mapping in one shared helper means the Desk form,
 * public apply form and self-service edit all speak the same shape
 * of validation rules.
 */
class MembershipCustomFields
{
    /**
     * Allowed field type keys. New types are additive — safe
     * to extend as the UI learns to render them.
     */
    public const TYPES = ['text', 'textarea', 'select', 'number', 'date', 'checkbox'];

    /**
     * Normalise the raw schema into a list of fully-populated
     * field shapes. Drops entries that have no `key` since those
     * are not addressable.
     *
     * @return list<array{key:string, label:string, type:string, required:bool, options:list<string>}>
     */
    public static function schemaFor(Organisation $organisation): array
    {
        $raw = $organisation->membership_custom_fields ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = isset($entry['key']) ? (string) $entry['key'] : '';
            if ($key === '') {
                continue;
            }

            $type = isset($entry['type']) ? (string) $entry['type'] : 'text';
            if (! in_array($type, self::TYPES, true)) {
                $type = 'text';
            }

            $options = [];
            if ($type === 'select' && isset($entry['options']) && is_array($entry['options'])) {
                $options = array_values(array_map('strval', $entry['options']));
            }

            $out[] = [
                'key' => $key,
                'label' => isset($entry['label']) ? (string) $entry['label'] : $key,
                'type' => $type,
                'required' => (bool) ($entry['required'] ?? false),
                'options' => $options,
            ];
        }

        return $out;
    }

    /**
     * Turn the schema into Laravel validation rules, prefixed so
     * calling code can nest them under a single array key (default
     * `custom_attributes`).
     *
     * @return array<string, list<string>>
     */
    public static function rules(Organisation $organisation, string $prefix = 'custom_attributes'): array
    {
        $rules = [];

        foreach (self::schemaFor($organisation) as $field) {
            $base = $field['required'] ? ['required'] : ['nullable'];

            $rules[$prefix.'.'.$field['key']] = match ($field['type']) {
                'number' => array_merge($base, ['numeric']),
                'date' => array_merge($base, ['date']),
                'checkbox' => array_merge($base, ['boolean']),
                'select' => array_merge($base, ['string', 'in:'.implode(',', $field['options'])]),
                'textarea' => array_merge($base, ['string', 'max:4000']),
                default => array_merge($base, ['string', 'max:255']),
            };
        }

        return $rules;
    }
}

<?php

declare(strict_types=1);

/**
 * Custom billing fields added to the checkout's address form.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop\Checkout;

/**
 * Validates and shapes the admin-defined custom fields (e.g. RUT, business
 * activity, receipt or invoice), stored as JSON in a single Configuration
 * value. Storage itself is left to the caller, so this class stays
 * unit-testable.
 */
final class CheckoutFields
{
    /**
     * Configuration key of the field definitions (JSON).
     */
    public const KEY = 'BILLMYSALES_CUSTOM_FIELDS';

    /**
     * Decodes the stored field definitions.
     *
     * @param string|false $raw Raw BILLMYSALES_CUSTOM_FIELDS value.
     * @return array<int, array{key: string, label: string, values: string[], required: bool}>
     */
    public static function from_raw($raw): array
    {
        $decoded = json_decode(is_string($raw) ? $raw : '', true);
        if (!is_array($decoded)) {
            return [];
        }
        $fields = [];
        foreach ($decoded as $field) {
            if (!is_array($field) || empty($field['key']) || empty($field['label'])) {
                continue;
            }
            $fields[] = [
                'key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'values' => isset($field['values']) && is_array($field['values']) ? array_map('strval', $field['values']) : [],
                'required' => !empty($field['required']),
            ];
        }
        return $fields;
    }

    /**
     * Validates the fields form. Rows without a label are dropped; an
     * existing field keeps its key (so previously saved address values
     * stay linked to it); a new field's key is derived from its label,
     * unique within the list.
     *
     * @param array<int, array{key?: string, label?: string, values?: string, required?: string}> $rows Raw form rows.
     * @return array<int, array{key: string, label: string, values: string[], required: bool}>
     */
    public static function sanitize(array $rows): array
    {
        $fields = [];
        $used_keys = [];

        foreach ($rows as $row) {
            if (empty($row['key'])) {
                continue;
            }
            $used_keys[] = self::slug((string) $row['key']);
        }
        foreach ($rows as $row) {
            $label = isset($row['label']) ? trim(strip_tags((string) $row['label'])) : '';
            if ('' === $label) {
                continue;
            }
            $key = !empty($row['key']) ? self::slug((string) $row['key']) : self::unique_key($label, $used_keys);
            $used_keys[] = $key;

            $values = [];
            $raw_values = isset($row['values']) ? trim((string) $row['values']) : '';
            if ('' !== $raw_values) {
                foreach (explode(',', $raw_values) as $value) {
                    $value = trim(strip_tags($value));
                    if ('' !== $value) {
                        $values[] = $value;
                    }
                }
            }

            $fields[] = [
                'key' => $key,
                'label' => $label,
                'values' => $values,
                'required' => !empty($row['required']),
            ];
        }

        return $fields;
    }

    /**
     * Labels of the required fields missing from a set of values.
     *
     * @param array<int, array{key: string, label: string, values: string[], required: bool}> $fields Field definitions.
     * @param array<string, string>                                                           $values Current values, by key.
     * @return string[]
     */
    public static function missing_required(array $fields, array $values): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!empty($field['required']) && empty($values[$field['key']])) {
                $missing[] = $field['label'];
            }
        }
        return $missing;
    }

    /**
     * Values of the fields in an order, in the payload's meta shape.
     *
     * @param array<int, array{key: string, label: string, values: string[], required: bool}> $fields Field definitions.
     * @param array<string, string>                                                           $values Current values, by key.
     * @return array<string, string>
     */
    public static function payload_meta(array $fields, array $values): array
    {
        $meta = [];
        foreach ($fields as $field) {
            if (isset($values[$field['key']]) && '' !== $values[$field['key']]) {
                $meta[$field['key']] = $values[$field['key']];
            }
        }
        return $meta;
    }

    /**
     * A URL/attribute-safe key from a label.
     *
     * @param string $label Label.
     * @return string
     */
    private static function slug(string $label): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($label)));
        $slug = trim((string) $slug, '-');
        return '' === $slug ? 'field' : $slug;
    }

    /**
     * A key from a label, with a numeric suffix when it's taken.
     *
     * @param string   $label Field label.
     * @param string[] $taken Keys already used.
     * @return string
     */
    private static function unique_key(string $label, array $taken): string
    {
        $base = self::slug($label);
        $key = $base;
        $i = 2;
        while (in_array($key, $taken, true)) {
            $key = $base . '-' . $i;
            ++$i;
        }
        return $key;
    }
}

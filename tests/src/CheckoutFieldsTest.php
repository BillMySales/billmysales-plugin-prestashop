<?php

declare(strict_types=1);

/**
 * Tests of the custom checkout fields.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Checkout\CheckoutFields;

/**
 * @covers \BillMySales\PrestaShop\Checkout\CheckoutFields
 */
final class CheckoutFieldsTest extends TestCase
{
    /**
     * Valid stored JSON decodes into field definitions.
     *
     * @return void
     */
    public function test_from_raw_with_valid_json(): void
    {
        $json = '[{"key":"tax-id","label":"Tax id","values":[],"required":true},'
            . '{"key":"doc","label":"Document","values":["Receipt","Invoice"],"required":false}]';
        $fields = CheckoutFields::from_raw($json);
        $this->assertSame([
            ['key' => 'tax-id', 'label' => 'Tax id', 'values' => [], 'required' => true],
            ['key' => 'doc', 'label' => 'Document', 'values' => ['Receipt', 'Invoice'], 'required' => false],
        ], $fields);
    }

    /**
     * Missing, non-JSON or non-array Configuration values decode to no
     * fields.
     *
     * @return void
     */
    public function test_from_raw_with_invalid_input(): void
    {
        $this->assertSame([], CheckoutFields::from_raw(false));
        $this->assertSame([], CheckoutFields::from_raw('not json'));
        $this->assertSame([], CheckoutFields::from_raw('"a string"'));
    }

    /**
     * Rows missing a key, a label, or that aren't arrays, are dropped.
     *
     * @return void
     */
    public function test_from_raw_drops_incomplete_rows(): void
    {
        $json = '["not-an-array",{"key":"","label":"X"},{"key":"x","label":""},{"key":"x","label":"X"}]';
        $fields = CheckoutFields::from_raw($json);
        $this->assertSame([['key' => 'x', 'label' => 'X', 'values' => [], 'required' => false]], $fields);
    }

    /**
     * A new field's key is derived from its label.
     *
     * @return void
     */
    public function test_sanitize_derives_key_from_label(): void
    {
        $fields = CheckoutFields::sanitize([
            ['label' => 'Tax Id', 'values' => '', 'required' => '1'],
        ]);
        $this->assertSame([
            ['key' => 'tax-id', 'label' => 'Tax Id', 'values' => [], 'required' => true],
        ], $fields);
    }

    /**
     * An existing field (posted with its key) keeps that key even if its
     * label changed.
     *
     * @return void
     */
    public function test_sanitize_keeps_existing_key(): void
    {
        $fields = CheckoutFields::sanitize([
            ['key' => 'tax-id', 'label' => 'Renamed label', 'values' => '', 'required' => ''],
        ]);
        $this->assertSame('tax-id', $fields[0]['key']);
        $this->assertFalse($fields[0]['required']);
    }

    /**
     * Comma-separated values are split and trimmed; an empty value list
     * means a free text field.
     *
     * @return void
     */
    public function test_sanitize_splits_values(): void
    {
        $fields = CheckoutFields::sanitize([
            ['label' => 'Document', 'values' => ' Receipt , Invoice ,, <b>Boleta</b> '],
        ]);
        $this->assertSame(['Receipt', 'Invoice', 'Boleta'], $fields[0]['values']);
    }

    /**
     * A row without a label is dropped (e.g. an empty row from the admin
     * form, or one left over after removing a field client-side).
     *
     * @return void
     */
    public function test_sanitize_drops_rows_without_a_label(): void
    {
        $this->assertSame([], CheckoutFields::sanitize([['label' => '  ']]));
        $this->assertSame([], CheckoutFields::sanitize([[]]));
    }

    /**
     * Two fields with the same label get distinct, numbered keys; a label
     * with no alphanumeric characters slugs to "field".
     *
     * @return void
     */
    public function test_sanitize_generates_unique_keys(): void
    {
        $fields = CheckoutFields::sanitize([
            ['label' => 'RUT'],
            ['label' => 'RUT'],
            ['label' => '---'],
        ]);
        $this->assertSame(['rut', 'rut-2', 'field'], array_column($fields, 'key'));
    }

    /**
     * A new field's generated key never collides with an existing field's
     * key posted later in the same form.
     *
     * @return void
     */
    public function test_sanitize_avoids_colliding_with_an_existing_key(): void
    {
        $fields = CheckoutFields::sanitize([
            ['label' => 'RUT'],
            ['key' => 'rut', 'label' => 'RUT (existing)'],
        ]);
        $this->assertSame(['rut-2', 'rut'], array_column($fields, 'key'));
    }

    /**
     * Labels of the required fields missing a value.
     *
     * @return void
     */
    public function test_missing_required(): void
    {
        $fields = [
            ['key' => 'a', 'label' => 'A', 'values' => [], 'required' => true],
            ['key' => 'b', 'label' => 'B', 'values' => [], 'required' => true],
            ['key' => 'c', 'label' => 'C', 'values' => [], 'required' => false],
        ];
        $this->assertSame(['A'], CheckoutFields::missing_required($fields, ['b' => 'x']));
        $this->assertSame([], CheckoutFields::missing_required($fields, ['a' => 'x', 'b' => 'y']));
    }

    /**
     * Only fields with a non-empty value are included in the payload meta.
     *
     * @return void
     */
    public function test_payload_meta(): void
    {
        $fields = [
            ['key' => 'a', 'label' => 'A', 'values' => [], 'required' => false],
            ['key' => 'b', 'label' => 'B', 'values' => [], 'required' => false],
        ];
        $meta = CheckoutFields::payload_meta($fields, ['a' => 'x', 'b' => '']);
        $this->assertSame(['a' => 'x'], $meta);
    }
}

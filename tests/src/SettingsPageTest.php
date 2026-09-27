<?php

declare(strict_types=1);

/**
 * Tests of the settings page's view model.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Admin\SettingsPage;
use BillMySales\PrestaShop\Settings;

/**
 * @covers \BillMySales\PrestaShop\Admin\SettingsPage
 */
final class SettingsPageTest extends TestCase
{
    /**
     * The form definition translates every label and carries the order
     * states as the "notify" checkbox's query.
     *
     * @return void
     */
    public function test_form_definition(): void
    {
        $order_states = [['id_order_state' => 2, 'name' => 'Payment accepted']];
        $translate = static fn (string $s): string => strtoupper($s);
        $definition = SettingsPage::form_definition($translate, $order_states);

        $this->assertSame('NOTIFICATION SETTINGS', $definition['form']['legend']['title']);
        $this->assertSame('SAVE', $definition['form']['submit']['title']);

        $statuses_input = end($definition['form']['input']);
        $this->assertSame($order_states, $statuses_input['values']['query']);
        $this->assertSame('id_order_state', $statuses_input['values']['id']);

        $payload_input = $definition['form']['input'][3];
        $this->assertSame(Settings::FORMAT_LEGACY, $payload_input['values'][0]['value']);
        $this->assertSame(Settings::FORMAT_WEBSERVICE, $payload_input['values'][1]['value']);
    }

    /**
     * The form values include the settings and one checkbox per order
     * state, checked only when its id is selected.
     *
     * @return void
     */
    public function test_form_values(): void
    {
        $settings = [
            'url' => 'https://example.com/hook',
            'secret' => 's3cret',
            'statuses' => ['2'],
            'active' => true,
            'payload_format' => Settings::FORMAT_LEGACY,
        ];
        $order_states = [
            ['id_order_state' => 2, 'name' => 'Payment accepted'],
            ['id_order_state' => 3, 'name' => 'Cancelled'],
        ];

        $values = SettingsPage::form_values($settings, $order_states);

        $this->assertSame([
            Settings::KEY_ACTIVE => true,
            Settings::KEY_URL => 'https://example.com/hook',
            Settings::KEY_SECRET => 's3cret',
            Settings::KEY_PAYLOAD_FORMAT => Settings::FORMAT_LEGACY,
            Settings::KEY_STATUSES . '_2' => true,
            Settings::KEY_STATUSES . '_3' => false,
        ], $values);
    }

    /**
     * Saved fields are shown with their values comma-joined.
     *
     * @return void
     */
    public function test_fields_view_with_saved_fields(): void
    {
        $rows = SettingsPage::fields_view([
            ['key' => 'doc', 'label' => 'Document', 'values' => ['Receipt', 'Invoice'], 'required' => false],
        ]);
        $this->assertSame([
            ['index' => 0, 'key' => 'doc', 'label' => 'Document', 'values' => 'Receipt, Invoice', 'required' => false],
        ], $rows);
    }

    /**
     * With no fields saved yet, one empty row is shown so the form isn't
     * blank.
     *
     * @return void
     */
    public function test_fields_view_with_no_fields(): void
    {
        $rows = SettingsPage::fields_view([]);
        $this->assertSame([
            ['index' => 0, 'key' => '', 'label' => '', 'values' => '', 'required' => false],
        ], $rows);
    }
}

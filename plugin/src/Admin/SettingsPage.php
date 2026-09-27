<?php

declare(strict_types=1);

/**
 * View model of the settings page (Modules > BillMySales > Configure).
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop\Admin;

use BillMySales\PrestaShop\Settings;

/**
 * Builds the HelperForm structure and values of the "Settings" tab, and the
 * template rows of the "Checkout fields" tab, so the value preparation
 * stays unit-testable (rendering the form, and fetching the checkout
 * fields' template, are left to the module).
 */
final class SettingsPage
{
    /**
     * HelperForm structure of the "Settings" tab.
     *
     * @param callable(string):string                                      $translate    Translates a label (the module's own l()).
     * @param array<int, array{id_order_state: int|string, name: string}>  $order_states Every order state, in the employee's language.
     * @return array<string, mixed>
     */
    public static function form_definition(callable $translate, array $order_states): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $translate('Notification settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $translate('Deliveries'),
                        'name' => Settings::KEY_ACTIVE,
                        'is_bool' => true,
                        'desc' => $translate('The integration must also be active in BillMySales for orders to be sent.'),
                        'values' => [
                            ['id' => 'active_on', 'value' => true, 'label' => $translate('Active')],
                            ['id' => 'active_off', 'value' => false, 'label' => $translate('Inactive')],
                        ],
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'label' => $translate('Notification URL'),
                        'name' => Settings::KEY_URL,
                        'prefix' => '<i class="icon-exchange"></i>',
                        'desc' => $translate('The webhook URL given by BillMySales.'),
                        'required' => true,
                    ],
                    [
                        'col' => 4,
                        'type' => 'password',
                        'label' => $translate('Secret'),
                        'name' => Settings::KEY_SECRET,
                        'prefix' => '<i class="icon-key"></i>',
                        'desc' => $translate('The secret shared with BillMySales, used to sign each notification.'),
                        'required' => true,
                    ],
                    [
                        'type' => 'radio',
                        'label' => $translate('Payload format'),
                        'name' => Settings::KEY_PAYLOAD_FORMAT,
                        'desc' => $translate('"Standard" is what BillMySales parses today. "Webservice" sends PrestaShop\'s own webservice representation of the order instead; use it only if your BillMySales integration already expects it.'),
                        'values' => [
                            ['id' => 'payload_format_legacy', 'value' => Settings::FORMAT_LEGACY, 'label' => $translate('Standard')],
                            ['id' => 'payload_format_webservice', 'value' => Settings::FORMAT_WEBSERVICE, 'label' => $translate('Webservice')],
                        ],
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $translate('Order states that notify'),
                        'name' => Settings::KEY_STATUSES,
                        'desc' => $translate('BillMySales is notified only when an order reaches one of these states. Select only the state that means the payment was accepted, so an order is not sent more than once.'),
                        'values' => [
                            'query' => $order_states,
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $translate('Save'),
                ],
            ],
        ];
    }

    /**
     * The values HelperForm shows in the "Settings" tab's fields.
     *
     * @param array{url: string, secret: string, statuses: string[], active: bool, payload_format: string} $settings     Settings.
     * @param array<int, array{id_order_state: int|string, name: string}>                                  $order_states Every order state, in the employee's language.
     * @return array<string, mixed>
     */
    public static function form_values(array $settings, array $order_states): array
    {
        $values = [
            Settings::KEY_ACTIVE => $settings['active'],
            Settings::KEY_URL => $settings['url'],
            Settings::KEY_SECRET => $settings['secret'],
            Settings::KEY_PAYLOAD_FORMAT => $settings['payload_format'],
        ];
        foreach ($order_states as $state) {
            $id = (int) $state['id_order_state'];
            $values[Settings::KEY_STATUSES . '_' . $id] = in_array((string) $id, $settings['statuses'], true);
        }
        return $values;
    }

    /**
     * Template rows of the "Checkout fields" tab (also used for the hidden
     * row template a script clones to add a field).
     *
     * @param array<int, array{key: string, label: string, values: string[], required: bool}> $fields Field definitions.
     * @return array<int, array{index: int, key: string, label: string, values: string, required: bool}>
     */
    public static function fields_view(array $fields): array
    {
        if ([] === $fields) {
            $fields = [['key' => '', 'label' => '', 'values' => [], 'required' => false]];
        }
        $rows = [];
        foreach (array_values($fields) as $index => $field) {
            $rows[] = [
                'index' => $index,
                'key' => $field['key'],
                'label' => $field['label'],
                'values' => implode(', ', $field['values']),
                'required' => $field['required'],
            ];
        }
        return $rows;
    }
}

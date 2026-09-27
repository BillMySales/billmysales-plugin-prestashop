<?php

declare(strict_types=1);

/**
 * View model of the order detail page's "Billing data" block.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop\Admin;

use BillMySales\PrestaShop\Webhook\Delivery;

/**
 * Shapes the checkout fields and the last known delivery status of an order
 * into the plain array the block's template renders (fetching the template
 * is left to the module).
 */
final class OrderBlock
{
    /**
     * Template variables of the block.
     *
     * @param callable(string):string                                                          $translate       Translates a label (the module's own l()).
     * @param array<int, array{key: string, label: string, values: string[], required: bool}> $fields Field definitions.
     * @param array<string, string>                                                           $values Current values, by key.
     * @param array{status: string, detail: string, event: string, delivery_id: string, attempts: int, updated_at: string}|null $delivery_status Last known delivery status, if any.
     * @param bool                                                                             $can_resend Whether "Send to BillMySales" is offered.
     * @return array<string, mixed>
     */
    public static function view(callable $translate, array $fields, array $values, ?array $delivery_status, bool $can_resend): array
    {
        $rows = [];
        foreach ($fields as $field) {
            $rows[] = [
                'key' => $field['key'],
                'label' => $field['label'],
                'values' => $field['values'],
                'required' => $field['required'],
                'value' => $values[$field['key']] ?? '',
            ];
        }
        $labels = [
            Delivery::RESULT_DELIVERED => $translate('Sent'),
            Delivery::RESULT_RETRY => $translate('Retrying'),
            Delivery::RESULT_GIVEN_UP => $translate('Not sent, no more retries'),
            Delivery::RESULT_REJECTED => $translate('Rejected'),
        ];
        return [
            'fields' => $rows,
            'delivery_status' => null === $delivery_status ? null : [
                'label' => $labels[$delivery_status['status']] ?? $delivery_status['status'],
                'detail' => $delivery_status['detail'],
                'updated_at' => $delivery_status['updated_at'],
            ],
            'can_resend' => $can_resend,
        ];
    }
}

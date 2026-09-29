<?php

declare(strict_types=1);

/**
 * The order payload sent to BillMySales.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop\Webhook;

/**
 * Builds the payload in either of the two formats the module supports (see
 * Settings::KEY_PAYLOAD_FORMAT): "legacy", the plain order data the module
 * assembles from Order, Cart, Address, Customer, Carrier and Shop (the
 * shape BillMySales' PrestaShop datasource has parsed since the 1.x
 * module), or "webservice", PrestaShop's own webservice representation of
 * the order (built by the module from Order::getWebserviceParameters() and
 * WebserviceOutputBuilder, without an HTTP call or a webservice key). Both
 * remove secrets and carry the checkout fields' values. The payload is
 * built from data read when the delivery runs, not when it was queued.
 */
final class Payload
{
    /**
     * Customer fields never sent: the password hash and the autologin
     * token, neither needed to bill an order.
     */
    public const CUSTOMER_EXCLUDED = ['passwd', 'secure_key'];

    /**
     * Shop fields never sent: the theme's own configuration, not needed to
     * bill an order.
     */
    public const SHOP_EXCLUDED = ['theme'];

    /**
     * Key the checkout fields' values are attached under, in either format.
     */
    public const META_KEY = 'billmysales_custom_fields';

    /**
     * Builds the "legacy" payload.
     *
     * @param array<string, mixed> $order Plain order data, as assembled by the module.
     * @param array<string, string> $meta Checkout fields' values, by key.
     * @return array<string, mixed>
     */
    public static function build_legacy(array $order, array $meta): array
    {
        // PrestaShop 8 loads the order's state id as a string, 9 as an
        // integer: BillMySales reads an integer whichever loaded it.
        if (isset($order['current_state']) && is_numeric($order['current_state'])) {
            $order['current_state'] = (int) $order['current_state'];
        }
        if (isset($order['customer']) && is_array($order['customer'])) {
            $order['customer'] = array_diff_key($order['customer'], array_flip(self::CUSTOMER_EXCLUDED));
        }
        if (isset($order['shop']) && is_array($order['shop'])) {
            $order['shop'] = array_diff_key($order['shop'], array_flip(self::SHOP_EXCLUDED));
        }
        $order[self::META_KEY] = $meta;
        return $order;
    }

    /**
     * Builds the "webservice" payload.
     *
     * @param array<string, mixed>  $webservice The order's webservice representation, as the module built it.
     * @param array<string, string> $meta       Checkout fields' values, by key.
     * @return array<string, mixed>
     */
    public static function build_webservice(array $webservice, array $meta): array
    {
        $webservice = self::strip_secrets($webservice);
        $webservice[self::META_KEY] = $meta;
        return $webservice;
    }

    /**
     * Removes known secret fields, at any depth (defense in depth: the
     * webservice representation is built from PrestaShop's own field
     * whitelist, which already leaves out passwords).
     *
     * @param array<string, mixed> $data Data.
     * @return array<string, mixed>
     */
    private static function strip_secrets(array $data): array
    {
        $excluded = array_flip(array_merge(self::CUSTOMER_EXCLUDED, self::SHOP_EXCLUDED));
        foreach ($data as $key => $value) {
            if (isset($excluded[$key])) {
                unset($data[$key]);
                continue;
            }
            if (is_array($value)) {
                $data[$key] = self::strip_secrets($value);
            }
        }
        return $data;
    }
}

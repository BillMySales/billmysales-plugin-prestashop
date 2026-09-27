<?php

declare(strict_types=1);

/**
 * Tests of the order payload.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Webhook\Payload;

/**
 * @covers \BillMySales\PrestaShop\Webhook\Payload
 */
final class PayloadTest extends TestCase
{
    /**
     * The legacy payload strips the customer's password and autologin
     * token, the shop's theme, and carries the checkout fields' meta.
     *
     * @return void
     */
    public function test_build_legacy_strips_secrets_and_adds_meta(): void
    {
        $order = [
            'id_order' => 7,
            'customer' => ['id' => 1, 'passwd' => 'hash', 'secure_key' => 'token', 'firstname' => 'Ana'],
            'shop' => ['id' => 1, 'theme' => ['name' => 'classic'], 'name' => 'My shop'],
        ];
        $payload = Payload::build_legacy($order, ['tax-id' => '11.111.111-1']);

        $this->assertSame(['id' => 1, 'firstname' => 'Ana'], $payload['customer']);
        $this->assertSame(['id' => 1, 'name' => 'My shop'], $payload['shop']);
        $this->assertSame(['tax-id' => '11.111.111-1'], $payload[Payload::META_KEY]);
        $this->assertSame(7, $payload['id_order']);
    }

    /**
     * Missing "customer"/"shop" keys, or ones that aren't arrays, are left
     * as is.
     *
     * @return void
     */
    public function test_build_legacy_without_customer_or_shop(): void
    {
        $payload = Payload::build_legacy(['id_order' => 7], []);
        $this->assertSame(['id_order' => 7, Payload::META_KEY => []], $payload);

        $payload = Payload::build_legacy(['customer' => 'not-an-array'], []);
        $this->assertSame('not-an-array', $payload['customer']);
    }

    /**
     * The webservice payload strips secrets at any depth (defense in
     * depth), and carries the checkout fields' meta.
     *
     * @return void
     */
    public function test_build_webservice_strips_secrets_at_any_depth(): void
    {
        $webservice = [
            'order' => [
                'id' => 7,
                'customer' => ['passwd' => 'hash', 'secure_key' => 'token', 'firstname' => 'Ana'],
            ],
            'theme' => 'classic',
        ];
        $payload = Payload::build_webservice($webservice, ['tax-id' => '11.111.111-1']);

        $this->assertSame(['id' => 7, 'customer' => ['firstname' => 'Ana']], $payload['order']);
        $this->assertArrayNotHasKey('theme', $payload);
        $this->assertSame(['tax-id' => '11.111.111-1'], $payload[Payload::META_KEY]);
    }
}

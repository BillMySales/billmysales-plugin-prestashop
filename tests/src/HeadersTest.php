<?php

declare(strict_types=1);

/**
 * Tests of the delivery's HTTP headers.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Webhook\Headers;

/**
 * @covers \BillMySales\PrestaShop\Webhook\Headers
 */
final class HeadersTest extends TestCase
{
    /**
     * The signature is the base64 of the HMAC-SHA256 of the body with the
     * secret.
     *
     * @return void
     */
    public function test_signature(): void
    {
        $expected = base64_encode(hash_hmac('sha256', '{"a":1}', 's3cret', true));
        $this->assertSame($expected, Headers::signature('{"a":1}', 's3cret'));
    }

    /**
     * The standard headers, plus the legacy signature header the 1.x
     * module sent (what BillMySales' PrestaShop datasource reads today);
     * the secret itself is never included.
     *
     * @return void
     */
    public function test_build(): void
    {
        $headers = Headers::build('{"a":1}', 's3cret', 'order.status_changed', 'uuid-1', 'https://shop.example/', '9.1.5', '2.0.0');

        $signature = base64_encode(hash_hmac('sha256', '{"a":1}', 's3cret', true));
        $this->assertSame([
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'BillMySales-prestashop/2.0.0',
            'X-BillMySales-Signature' => $signature,
            'X-BillMySales-Platform' => 'prestashop',
            'X-BillMySales-Platform-Version' => '9.1.5',
            'X-BillMySales-Plugin-Version' => '2.0.0',
            'X-BillMySales-Source' => 'https://shop.example/',
            'X-BillMySales-Event' => 'order.status_changed',
            'X-BillMySales-Delivery' => 'uuid-1',
            'X-PRESTASHOPBMS-HMAC-SHA256' => $signature,
        ], $headers);

        foreach ($headers as $value) {
            $this->assertStringNotContainsString('s3cret', $value);
        }
    }
}

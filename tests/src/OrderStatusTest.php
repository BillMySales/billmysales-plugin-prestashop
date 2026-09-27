<?php

declare(strict_types=1);

/**
 * Tests of the per-order delivery status.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Webhook\OrderStatus;

/**
 * @covers \BillMySales\PrestaShop\Webhook\OrderStatus
 */
final class OrderStatusTest extends TestCase
{
    /**
     * record() replaces the order's row.
     *
     * @return void
     */
    public function test_record(): void
    {
        $executed = [];
        $store = new OrderStatus(
            static fn (): array => [],
            static function (string $sql) use (&$executed): bool {
                $executed[] = $sql;
                return true;
            },
            'ps_'
        );

        $store->record(7, 'delivered', 'HTTP 200', 'order.status_changed', 'uuid-1', 0, '2026-01-01 10:00:00');

        $this->assertStringContainsString('REPLACE INTO `ps_billmysales_order_status`', $executed[0]);
        $this->assertStringContainsString('(7,', $executed[0]);
        $this->assertStringContainsString("'delivered'", $executed[0]);
        $this->assertStringContainsString("'HTTP 200'", $executed[0]);
        $this->assertStringContainsString('0,', $executed[0]);
    }

    /**
     * get() returns the order's row, typed, or null when there is none.
     *
     * @return void
     */
    public function test_get(): void
    {
        $selected = [];
        $store = new OrderStatus(
            static function (string $sql) use (&$selected): array {
                $selected[] = $sql;
                return [[
                    'id_order' => '7',
                    'status' => 'delivered',
                    'detail' => 'HTTP 200',
                    'event' => 'order.status_changed',
                    'delivery_id' => 'uuid-1',
                    'attempts' => '2',
                    'updated_at' => '2026-01-01 10:00:00',
                ]];
            },
            static fn (): bool => true,
            'ps_'
        );

        $status = $store->get(7);

        $this->assertStringContainsString('FROM `ps_billmysales_order_status` WHERE `id_order` = 7', $selected[0]);
        $this->assertSame([
            'status' => 'delivered',
            'detail' => 'HTTP 200',
            'event' => 'order.status_changed',
            'delivery_id' => 'uuid-1',
            'attempts' => 2,
            'updated_at' => '2026-01-01 10:00:00',
        ], $status);
    }

    /**
     * get() returns null when the order has no recorded status.
     *
     * @return void
     */
    public function test_get_returns_null_when_missing(): void
    {
        $store = new OrderStatus(
            static fn (): array => [],
            static fn (): bool => true
        );
        $this->assertNull($store->get(7));
    }

    /**
     * delete() removes the order's row.
     *
     * @return void
     */
    public function test_delete(): void
    {
        $executed = [];
        $store = new OrderStatus(
            static fn (): array => [],
            static function (string $sql) use (&$executed): bool {
                $executed[] = $sql;
                return true;
            },
            'ps_'
        );

        $store->delete(7);

        $this->assertSame('DELETE FROM `ps_billmysales_order_status` WHERE `id_order` = 7', $executed[0]);
    }
}

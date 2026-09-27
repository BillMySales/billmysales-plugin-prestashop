<?php

declare(strict_types=1);

/**
 * Tests of the delivery queue.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Webhook\Queue;

/**
 * @covers \BillMySales\PrestaShop\Webhook\Queue
 */
final class QueueTest extends TestCase
{
    /**
     * enqueue() inserts a row for the order, event and delivery id given,
     * at attempt 0, due right away, with the shop's table prefix.
     *
     * @return void
     */
    public function test_enqueue(): void
    {
        $executed = [];
        $queue = new Queue(
            static fn (): array => [],
            static function (string $sql) use (&$executed): bool {
                $executed[] = $sql;
                return true;
            },
            'ps_'
        );

        $queue->enqueue(7, "order.status_changed", "uuid-'1", '2026-01-01 10:00:00');

        $this->assertCount(1, $executed);
        $this->assertStringContainsString('INSERT INTO `ps_billmysales_delivery_queue`', $executed[0]);
        $this->assertStringContainsString('(7,', $executed[0]);
        $this->assertStringContainsString("'order.status_changed'", $executed[0]);
        $this->assertStringContainsString("uuid-\\'1", $executed[0]);
        $this->assertStringContainsString(", 0, '2026-01-01 10:00:00', '2026-01-01 10:00:00')", $executed[0]);
    }

    /**
     * due() runs a SELECT filtered by next_attempt_at and limit, and maps
     * each row's fields to the expected types.
     *
     * @return void
     */
    public function test_due(): void
    {
        $selected = [];
        $queue = new Queue(
            static function (string $sql) use (&$selected): array {
                $selected[] = $sql;
                return [
                    ['id_queue' => '1', 'id_order' => '7', 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => '2'],
                ];
            },
            static fn (): bool => true,
            'ps_'
        );

        $rows = $queue->due('2026-01-01 10:00:00', 20);

        $this->assertStringContainsString('FROM `ps_billmysales_delivery_queue`', $selected[0]);
        $this->assertStringContainsString("next_attempt_at` <= '2026-01-01 10:00:00'", $selected[0]);
        $this->assertStringContainsString('LIMIT 20', $selected[0]);
        $this->assertSame([
            ['id_queue' => 1, 'id_order' => 7, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => 2],
        ], $rows);
    }

    /**
     * reschedule() updates the attempt and next_attempt_at of one row.
     *
     * @return void
     */
    public function test_reschedule(): void
    {
        $executed = [];
        $queue = new Queue(
            static fn (): array => [],
            static function (string $sql) use (&$executed): bool {
                $executed[] = $sql;
                return true;
            },
            'ps_'
        );

        $queue->reschedule(1, 2, '2026-01-01 10:05:00');

        $this->assertStringContainsString('UPDATE `ps_billmysales_delivery_queue`', $executed[0]);
        $this->assertStringContainsString('`attempt` = 2', $executed[0]);
        $this->assertStringContainsString("'2026-01-01 10:05:00'", $executed[0]);
        $this->assertStringContainsString('`id_queue` = 1', $executed[0]);
    }

    /**
     * delete() removes one row by id.
     *
     * @return void
     */
    public function test_delete(): void
    {
        $executed = [];
        $queue = new Queue(
            static fn (): array => [],
            static function (string $sql) use (&$executed): bool {
                $executed[] = $sql;
                return true;
            },
            'ps_'
        );

        $queue->delete(3);

        $this->assertSame("DELETE FROM `ps_billmysales_delivery_queue` WHERE `id_queue` = 3", $executed[0]);
    }

    /**
     * count_pending() returns the row count, or 0 when the query yields no
     * rows.
     *
     * @return void
     */
    public function test_count_pending(): void
    {
        $queue = new Queue(
            static fn (): array => [['n' => '4']],
            static fn (): bool => true,
            'ps_'
        );
        $this->assertSame(4, $queue->count_pending());

        $empty = new Queue(
            static fn (): array => [],
            static fn (): bool => true,
            'ps_'
        );
        $this->assertSame(0, $empty->count_pending());
    }

    /**
     * The table prefix defaults to none.
     *
     * @return void
     */
    public function test_default_prefix_is_empty(): void
    {
        $executed = [];
        $queue = new Queue(
            static fn (): array => [],
            static function (string $sql) use (&$executed): bool {
                $executed[] = $sql;
                return true;
            }
        );
        $queue->delete(1);
        $this->assertSame('DELETE FROM `billmysales_delivery_queue` WHERE `id_queue` = 1', $executed[0]);
    }
}

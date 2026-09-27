<?php

declare(strict_types=1);

/**
 * Pending deliveries, waiting for their next attempt.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop\Webhook;

/**
 * A small working queue (not a growing log: a row exists only while a
 * delivery is pending, and is removed once it succeeds, is rejected or runs
 * out of retries). Storage itself (a `Db::getInstance()` instance) is left
 * to the caller via the two callables this class receives, so it never
 * touches PrestaShop's runtime directly and stays unit-testable.
 */
final class Queue
{
    /**
     * The queue's table name, without the shop's prefix.
     */
    public const TABLE = 'billmysales_delivery_queue';

    /**
     * Runs a SELECT and returns its rows.
     *
     * @var callable(string):array<int, array<string, mixed>>
     */
    private $select;

    /**
     * Runs an INSERT, UPDATE or DELETE.
     *
     * @var callable(string):bool
     */
    private $execute;

    /**
     * The table's name, with the shop's prefix.
     *
     * @var string
     */
    private $table;

    /**
     * @param callable(string):array<int, array<string, mixed>> $select      Runs a SELECT and returns its rows.
     * @param callable(string):bool                              $execute     Runs an INSERT, UPDATE or DELETE.
     * @param string                                              $db_prefix   The shop's table prefix (e.g. "ps_").
     */
    public function __construct(callable $select, callable $execute, string $db_prefix = '')
    {
        $this->select = $select;
        $this->execute = $execute;
        $this->table = $db_prefix . self::TABLE;
    }

    /**
     * Queues a new delivery.
     *
     * @param int    $id_order    Order id.
     * @param string $event       Event name.
     * @param string $delivery_id UUID of the notification.
     * @param string $now         Current date and time (Y-m-d H:i:s).
     * @return void
     */
    public function enqueue(int $id_order, string $event, string $delivery_id, string $now): void
    {
        ($this->execute)(sprintf(
            "INSERT INTO `%s` (`id_order`, `event`, `delivery_id`, `attempt`, `next_attempt_at`, `created_at`) "
                . "VALUES (%d, '%s', '%s', 0, '%s', '%s')",
            $this->table,
            $id_order,
            \pSQL($event),
            \pSQL($delivery_id),
            \pSQL($now),
            \pSQL($now)
        ));
    }

    /**
     * Deliveries due for an attempt, oldest first.
     *
     * @param string $now   Current date and time (Y-m-d H:i:s).
     * @param int    $limit Maximum rows.
     * @return array<int, array{id_queue: int, id_order: int, event: string, delivery_id: string, attempt: int}>
     */
    public function due(string $now, int $limit): array
    {
        $rows = (array) ($this->select)(sprintf(
            "SELECT `id_queue`, `id_order`, `event`, `delivery_id`, `attempt` FROM `%s` "
                . "WHERE `next_attempt_at` <= '%s' ORDER BY `next_attempt_at` ASC LIMIT %d",
            $this->table,
            \pSQL($now),
            $limit
        ));
        return array_map(
            static fn (array $row): array => [
                    'id_queue' => (int) $row['id_queue'],
                    'id_order' => (int) $row['id_order'],
                    'event' => (string) $row['event'],
                    'delivery_id' => (string) $row['delivery_id'],
                    'attempt' => (int) $row['attempt'],
                ],
            $rows
        );
    }

    /**
     * Schedules the next attempt.
     *
     * @param int    $id_queue        Queue row id.
     * @param int    $attempt         Retries done so far (including this one).
     * @param string $next_attempt_at Next attempt's date and time (Y-m-d H:i:s).
     * @return void
     */
    public function reschedule(int $id_queue, int $attempt, string $next_attempt_at): void
    {
        ($this->execute)(sprintf(
            "UPDATE `%s` SET `attempt` = %d, `next_attempt_at` = '%s' WHERE `id_queue` = %d",
            $this->table,
            $attempt,
            \pSQL($next_attempt_at),
            $id_queue
        ));
    }

    /**
     * Removes a queue row: the delivery succeeded, was rejected, ran out of
     * retries, or its order no longer exists.
     *
     * @param int $id_queue Queue row id.
     * @return void
     */
    public function delete(int $id_queue): void
    {
        ($this->execute)(sprintf('DELETE FROM `%s` WHERE `id_queue` = %d', $this->table, $id_queue));
    }

    /**
     * Number of pending deliveries.
     *
     * @return int
     */
    public function count_pending(): int
    {
        $rows = (array) ($this->select)(sprintf('SELECT COUNT(*) AS `n` FROM `%s`', $this->table));
        return isset($rows[0]['n']) ? (int) $rows[0]['n'] : 0;
    }
}

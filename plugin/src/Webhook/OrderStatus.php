<?php

declare(strict_types=1);

/**
 * Last known delivery status of an order.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop\Webhook;

/**
 * One row per order that has ever had a delivery attempt (bounded by the
 * shop's order count, not by the number of attempts: a new attempt
 * replaces the previous row, it never grows a log), so the order detail
 * page can show BillMySales' last known status without keeping the queue
 * row around after it succeeds, is rejected or runs out of retries.
 * Storage itself is left to the caller via the two callables this class
 * receives, so it stays unit-testable.
 */
final class OrderStatus
{
    /**
     * The status table's name, without the shop's prefix.
     */
    public const TABLE = 'billmysales_order_status';

    /**
     * Runs a SELECT and returns its rows.
     *
     * @var callable(string):array<int, array<string, mixed>>
     */
    private $select;

    /**
     * Runs an INSERT, UPDATE, REPLACE or DELETE.
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
     * @param callable(string):array<int, array<string, mixed>> $select    Runs a SELECT and returns its rows.
     * @param callable(string):bool                              $execute   Runs an INSERT, UPDATE, REPLACE or DELETE.
     * @param string                                              $db_prefix The shop's table prefix (e.g. "ps_").
     */
    public function __construct(callable $select, callable $execute, string $db_prefix = '')
    {
        $this->select = $select;
        $this->execute = $execute;
        $this->table = $db_prefix . self::TABLE;
    }

    /**
     * Records the outcome of a delivery attempt, replacing the previous one.
     *
     * @param int    $id_order    Order id.
     * @param string $status      Delivery::RESULT_* constant.
     * @param string $detail      Human-readable detail.
     * @param string $event       Event name.
     * @param string $delivery_id UUID of the notification.
     * @param int    $attempts    Retries done so far.
     * @param string $now         Current date and time (Y-m-d H:i:s).
     * @return void
     */
    public function record(int $id_order, string $status, string $detail, string $event, string $delivery_id, int $attempts, string $now): void
    {
        ($this->execute)(sprintf(
            "REPLACE INTO `%s` (`id_order`, `status`, `detail`, `event`, `delivery_id`, `attempts`, `updated_at`) "
                . "VALUES (%d, '%s', '%s', '%s', '%s', %d, '%s')",
            $this->table,
            $id_order,
            \pSQL($status),
            \pSQL($detail),
            \pSQL($event),
            \pSQL($delivery_id),
            $attempts,
            \pSQL($now)
        ));
    }

    /**
     * The last known status of an order, if any.
     *
     * @param int $id_order Order id.
     * @return array{status: string, detail: string, event: string, delivery_id: string, attempts: int, updated_at: string}|null
     */
    public function get(int $id_order): ?array
    {
        $rows = (array) ($this->select)(sprintf('SELECT * FROM `%s` WHERE `id_order` = %d', $this->table, $id_order));
        if ([] === $rows) {
            return null;
        }
        $row = $rows[0];
        return [
            'status' => (string) $row['status'],
            'detail' => (string) $row['detail'],
            'event' => (string) $row['event'],
            'delivery_id' => (string) $row['delivery_id'],
            'attempts' => (int) $row['attempts'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * Removes an order's status row (the order was deleted).
     *
     * @param int $id_order Order id.
     * @return void
     */
    public function delete(int $id_order): void
    {
        ($this->execute)(sprintf('DELETE FROM `%s` WHERE `id_order` = %d', $this->table, $id_order));
    }
}

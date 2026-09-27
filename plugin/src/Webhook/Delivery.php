<?php

declare(strict_types=1);

/**
 * Sends the queued deliveries, with retries.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop\Webhook;

use BillMySales\PrestaShop\Settings;

/**
 * Queues a delivery when an order reaches a selected state (or the merchant
 * asks to send it again), and sends the due ones. A queued delivery is
 * never sent from the request that queued it (the order state change, or
 * the merchant's request): sending happens only when process_due() runs
 * (the module's cron controller, PrestaShop's own cron job hook when
 * available, or opportunistically from the back office), so nothing waits
 * on BillMySales. Failed deliveries are retried with a growing delay;
 * BillMySales is idempotent, so a repeated delivery is harmless. The
 * payload is built from data read when the delivery runs, not when it was
 * queued.
 */
final class Delivery
{
    /**
     * Delay (seconds) before each retry; after the last one, the delivery
     * is given up: 1 min, 5 min, 30 min, 2 h, 12 h.
     */
    public const RETRY_DELAYS = [60, 300, 1800, 7200, 43200];

    /**
     * A delivery was sent.
     */
    public const RESULT_DELIVERED = 'delivered';

    /**
     * A delivery failed and will be retried.
     */
    public const RESULT_RETRY = 'retry';

    /**
     * A delivery failed and ran out of retries.
     */
    public const RESULT_GIVEN_UP = 'given_up';

    /**
     * BillMySales refused a delivery (not retried).
     */
    public const RESULT_REJECTED = 'rejected';

    /**
     * The order to deliver no longer exists.
     */
    public const RESULT_GONE = 'gone';

    /**
     * The queue.
     *
     * @var Queue
     */
    private $queue;

    /**
     * Returns the current date and time (Y-m-d H:i:s).
     *
     * @var callable():string
     */
    private $now;

    /**
     * Generates a UUID for a new delivery.
     *
     * @var callable():string
     */
    private $uuid;

    /**
     * @param Queue              $queue Queue.
     * @param callable():string  $now   Returns the current date and time (Y-m-d H:i:s).
     * @param callable():string  $uuid  Generates a UUID for a new delivery.
     */
    public function __construct(Queue $queue, callable $now, callable $uuid)
    {
        $this->queue = $queue;
        $this->now = $now;
        $this->uuid = $uuid;
    }

    /**
     * Queues a new delivery.
     *
     * @param int    $id_order Order id.
     * @param string $event    Event name.
     * @return void
     */
    public function enqueue(int $id_order, string $event): void
    {
        $this->queue->enqueue($id_order, $event, ($this->uuid)(), ($this->now)());
    }

    /**
     * Sends the due deliveries.
     *
     * @param array{url: string, secret: string, statuses: string[], active: bool, payload_format: string} $settings Settings.
     * @param array{source: string, platform_version: string, plugin_version: string}                       $context  Request-time context (not user-configured).
     * @param callable(int):(array<string, mixed>|null)                                                     $legacy_order_data     Order id -> plain order data (Settings::FORMAT_LEGACY), or null if the order is gone.
     * @param callable(int):(array<string, mixed>|null)                                                     $webservice_order_data Order id -> the order's webservice representation (Settings::FORMAT_WEBSERVICE), or null if the order is gone.
     * @param callable(int):array<string, string>                                                           $checkout_fields_meta  Order id -> checkout fields' values, by key.
     * @param callable(string, string, array<string, string>):array{int, string}                            $http_post             (url, body, headers) -> [HTTP status (-1: network error), response body or error message].
     * @param callable(int, string, string, string, string, int):void                                       $on_result             (order id, event, delivery id, result, detail, attempt) -> void.
     * @param int                                                                                            $limit                 Maximum deliveries sent in one run.
     * @return void
     */
    public function process_due(
        array $settings,
        array $context,
        callable $legacy_order_data,
        callable $webservice_order_data,
        callable $checkout_fields_meta,
        callable $http_post,
        callable $on_result,
        int $limit = 20
    ): void {
        if (!Settings::is_ready($settings)) {
            return;
        }
        foreach ($this->queue->due(($this->now)(), $limit) as $row) {
            $this->process_one($row, $settings, $context, $legacy_order_data, $webservice_order_data, $checkout_fields_meta, $http_post, $on_result);
        }
    }

    /**
     * Processes one due delivery.
     *
     * @param array{id_queue: int, id_order: int, event: string, delivery_id: string, attempt: int}          $row                   Queue row.
     * @param array{url: string, secret: string, statuses: string[], active: bool, payload_format: string}   $settings              Settings.
     * @param array{source: string, platform_version: string, plugin_version: string}                        $context               Request-time context.
     * @param callable(int):(array<string, mixed>|null)                                                      $legacy_order_data     See process_due().
     * @param callable(int):(array<string, mixed>|null)                                                      $webservice_order_data See process_due().
     * @param callable(int):array<string, string>                                                            $checkout_fields_meta  See process_due().
     * @param callable(string, string, array<string, string>):array{int, string}                             $http_post             See process_due().
     * @param callable(int, string, string, string, string, int):void                                        $on_result             See process_due().
     * @return void
     */
    private function process_one(
        array $row,
        array $settings,
        array $context,
        callable $legacy_order_data,
        callable $webservice_order_data,
        callable $checkout_fields_meta,
        callable $http_post,
        callable $on_result
    ): void {
        $meta = $checkout_fields_meta($row['id_order']);
        if (Settings::FORMAT_WEBSERVICE === $settings['payload_format']) {
            $data = $webservice_order_data($row['id_order']);
            $payload = null === $data ? null : Payload::build_webservice($data, $meta);
        } else {
            $data = $legacy_order_data($row['id_order']);
            $payload = null === $data ? null : Payload::build_legacy($data, $meta);
        }

        if (null === $payload) {
            $this->queue->delete($row['id_queue']);
            $on_result($row['id_order'], $row['event'], $row['delivery_id'], self::RESULT_GONE, '', $row['attempt']);
            return;
        }

        $body = (string) json_encode($payload);
        $headers = Headers::build($body, $settings['secret'], $row['event'], $row['delivery_id'], $context['source'], $context['platform_version'], $context['plugin_version']);
        [$code, $detail] = $http_post($settings['url'], $body, $headers);

        if ($code >= 200 && $code < 300) {
            $this->queue->delete($row['id_queue']);
            $on_result($row['id_order'], $row['event'], $row['delivery_id'], self::RESULT_DELIVERED, 'HTTP ' . $code, $row['attempt']);
            return;
        }

        if (self::is_retryable($code) && isset(self::RETRY_DELAYS[$row['attempt']])) {
            $delay = self::RETRY_DELAYS[$row['attempt']];
            $attempt = $row['attempt'] + 1;
            $this->queue->reschedule($row['id_queue'], $attempt, date('Y-m-d H:i:s', strtotime(($this->now)()) + $delay));
            $on_result($row['id_order'], $row['event'], $row['delivery_id'], self::RESULT_RETRY, self::detail($code, $detail), $attempt);
            return;
        }

        $this->queue->delete($row['id_queue']);
        $result = self::is_retryable($code) ? self::RESULT_GIVEN_UP : self::RESULT_REJECTED;
        $on_result($row['id_order'], $row['event'], $row['delivery_id'], $result, self::detail($code, $detail), $row['attempt']);
    }

    /**
     * Whether an HTTP status is worth retrying: network errors, timeouts,
     * rate limits and server errors. Other client errors won't change on a
     * retry.
     *
     * @param int $code HTTP status (-1: network error).
     * @return bool
     */
    public static function is_retryable(int $code): bool
    {
        return -1 === $code || 408 === $code || 429 === $code || $code >= 500;
    }

    /**
     * A human-readable detail of the result: the network error message, or
     * the HTTP status and, for a client error, BillMySales' answer.
     *
     * @param int    $code   HTTP status (-1: network error).
     * @param string $detail Network error message, or the response body.
     * @return string
     */
    private static function detail(int $code, string $detail): string
    {
        if (-1 === $code) {
            return $detail;
        }
        $answer = trim(substr($detail, 0, 300));
        return 'HTTP ' . $code . ('' !== $answer && $code >= 400 && $code < 500 ? ': ' . $answer : '');
    }
}

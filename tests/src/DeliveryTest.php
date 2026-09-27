<?php

declare(strict_types=1);

/**
 * Tests of sending the queued deliveries, with retries.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Settings;
use BillMySales\PrestaShop\Webhook\Delivery;
use BillMySales\PrestaShop\Webhook\Queue;

/**
 * @covers \BillMySales\PrestaShop\Webhook\Delivery
 * @uses \BillMySales\PrestaShop\Settings
 * @uses \BillMySales\PrestaShop\Webhook\Queue
 * @uses \BillMySales\PrestaShop\Webhook\Headers
 * @uses \BillMySales\PrestaShop\Webhook\Payload
 */
final class DeliveryTest extends TestCase
{
    /**
     * enqueue() queues a row with a generated UUID and the current time.
     *
     * @return void
     */
    public function test_enqueue(): void
    {
        $executed = [];
        $queue = $this->queue($executed);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $delivery->enqueue(7, 'order.status_changed');

        $this->assertStringContainsString('(7,', $executed[0]);
        $this->assertStringContainsString("'order.status_changed'", $executed[0]);
        $this->assertStringContainsString('uuid-1', $executed[0]);
    }

    /**
     * Nothing is sent when settings aren't ready: the queue isn't even
     * read.
     *
     * @return void
     */
    public function test_process_due_does_nothing_when_not_ready(): void
    {
        $selected = [];
        $queue = $this->queue($executed, $selected);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $delivery->process_due(
            $this->settings(['active' => false]),
            $this->context(),
            $this->never_called(),
            $this->never_called(),
            $this->never_called(),
            $this->never_called(),
            $this->never_called()
        );

        $this->assertSame([], $selected);
    }

    /**
     * A due delivery whose order is gone is dropped, and reported as such.
     *
     * @return void
     */
    public function test_process_due_drops_a_delivery_whose_order_is_gone(): void
    {
        $executed = [];
        $queue = $this->queue($executed, $selected, [$this->row()]);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $results = [];
        $delivery->process_due(
            $this->settings(),
            $this->context(),
            static fn (int $id_order) => null,
            $this->never_called(),
            static fn (int $id_order): array => [],
            $this->never_called(),
            $this->record_result($results)
        );

        $this->assertStringContainsString('DELETE', $executed[0]);
        $this->assertSame([[7, 'order.status_changed', 'uuid-1', Delivery::RESULT_GONE, '', 0]], $results);
    }

    /**
     * A successful delivery (2xx) is removed from the queue and reported
     * delivered.
     *
     * @return void
     */
    public function test_process_due_delivers_successfully(): void
    {
        $executed = [];
        $queue = $this->queue($executed, $selected, [$this->row()]);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $posted = [];
        $results = [];
        $delivery->process_due(
            $this->settings(),
            $this->context(),
            static fn (int $id_order): array => ['id_order' => $id_order],
            $this->never_called(),
            static fn (int $id_order): array => ['tax-id' => '11.111.111-1'],
            function (string $url, string $body, array $headers) use (&$posted): array {
                $posted[] = [$url, $body, $headers];
                return [200, 'ok'];
            },
            $this->record_result($results)
        );

        $this->assertStringContainsString('DELETE', $executed[0]);
        $this->assertSame('https://billmysales.example/hook', $posted[0][0]);
        $body = json_decode($posted[0][1], true);
        $this->assertSame(['tax-id' => '11.111.111-1'], $body['billmysales_custom_fields']);
        $this->assertSame('uuid-1', $posted[0][2]['X-BillMySales-Delivery']);
        $this->assertSame([[7, 'order.status_changed', 'uuid-1', Delivery::RESULT_DELIVERED, 'HTTP 200', 0]], $results);
    }

    /**
     * The webservice payload format is used when configured.
     *
     * @return void
     */
    public function test_process_due_uses_the_webservice_format(): void
    {
        $executed = [];
        $queue = $this->queue($executed, $selected, [$this->row()]);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $posted = [];
        $delivery->process_due(
            $this->settings(['payload_format' => Settings::FORMAT_WEBSERVICE]),
            $this->context(),
            $this->never_called(),
            static fn (int $id_order): array => ['order' => ['id' => $id_order]],
            static fn (int $id_order): array => [],
            function (string $url, string $body, array $headers) use (&$posted): array {
                $posted[] = $body;
                return [200, 'ok'];
            },
            static function (): void {
            }
        );

        $body = json_decode($posted[0], true);
        $this->assertSame(['id' => 7], $body['order']);
    }

    /**
     * A retryable failure (e.g. a server error) reschedules the same
     * delivery with a growing delay, keeping the same delivery id.
     *
     * @return void
     */
    public function test_process_due_retries_a_retryable_failure(): void
    {
        $executed = [];
        $queue = $this->queue($executed, $selected, [$this->row(['attempt' => 2])]);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid('uuid-2'));

        $results = [];
        $delivery->process_due(
            $this->settings(),
            $this->context(),
            static fn (int $id_order): array => [],
            $this->never_called(),
            static fn (int $id_order): array => [],
            static fn (): array => [503, 'unavailable'],
            $this->record_result($results)
        );

        $this->assertStringContainsString('UPDATE', $executed[0]);
        $this->assertStringContainsString('`attempt` = 3', $executed[0]);
        $expected_next = date('Y-m-d H:i:s', strtotime('2026-01-01 10:00:00') + Delivery::RETRY_DELAYS[2]);
        $this->assertStringContainsString("'{$expected_next}'", $executed[0]);
        $this->assertSame([[7, 'order.status_changed', 'uuid-1', Delivery::RESULT_RETRY, 'HTTP 503', 3]], $results);
    }

    /**
     * A network error (-1) is retried, and its detail is the raw error
     * message (not prefixed with "HTTP").
     *
     * @return void
     */
    public function test_process_due_retries_a_network_error(): void
    {
        $executed = [];
        $queue = $this->queue($executed, $selected, [$this->row()]);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $results = [];
        $delivery->process_due(
            $this->settings(),
            $this->context(),
            static fn (int $id_order): array => [],
            $this->never_called(),
            static fn (int $id_order): array => [],
            static fn (): array => [-1, 'Could not resolve host'],
            $this->record_result($results)
        );

        $this->assertSame(Delivery::RESULT_RETRY, $results[0][3]);
        $this->assertSame('Could not resolve host', $results[0][4]);
    }

    /**
     * Once every retry is used up, a further retryable failure gives up
     * (deleted from the queue, reported given_up).
     *
     * @return void
     */
    public function test_process_due_gives_up_after_the_last_retry(): void
    {
        $executed = [];
        $last_attempt = count(Delivery::RETRY_DELAYS);
        $queue = $this->queue($executed, $selected, [$this->row(['attempt' => $last_attempt])]);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $results = [];
        $delivery->process_due(
            $this->settings(),
            $this->context(),
            static fn (int $id_order): array => [],
            $this->never_called(),
            static fn (int $id_order): array => [],
            static fn (): array => [500, 'still failing'],
            $this->record_result($results)
        );

        $this->assertStringContainsString('DELETE', $executed[0]);
        $this->assertSame(Delivery::RESULT_GIVEN_UP, $results[0][3]);
        $this->assertSame('HTTP 500', $results[0][4]);
        $this->assertSame($last_attempt, $results[0][5]);
    }

    /**
     * A non-retryable client error is rejected (deleted from the queue,
     * never retried), with BillMySales' answer in the detail.
     *
     * @return void
     */
    public function test_process_due_rejects_a_client_error(): void
    {
        $executed = [];
        $queue = $this->queue($executed, $selected, [$this->row()]);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $results = [];
        $delivery->process_due(
            $this->settings(),
            $this->context(),
            static fn (int $id_order): array => [],
            $this->never_called(),
            static fn (int $id_order): array => [],
            static fn (): array => [422, 'bad signature'],
            $this->record_result($results)
        );

        $this->assertStringContainsString('DELETE', $executed[0]);
        $this->assertSame(Delivery::RESULT_REJECTED, $results[0][3]);
        $this->assertSame('HTTP 422: bad signature', $results[0][4]);
    }

    /**
     * A client error with an empty body has no ": ..." suffix.
     *
     * @return void
     */
    public function test_process_due_rejects_with_no_body(): void
    {
        $queue = $this->queue($executed, $selected, [$this->row()]);
        $delivery = new Delivery($queue, $this->fixed_now(), $this->fixed_uuid());

        $results = [];
        $delivery->process_due(
            $this->settings(),
            $this->context(),
            static fn (int $id_order): array => [],
            $this->never_called(),
            static fn (int $id_order): array => [],
            static fn (): array => [401, '  '],
            $this->record_result($results)
        );

        $this->assertSame('HTTP 401', $results[0][4]);
    }

    /**
     * Retryable statuses: network errors, 408, 429 and every 5xx; other
     * client errors are not retried.
     *
     * @return void
     */
    public function test_is_retryable(): void
    {
        foreach ([-1, 408, 429, 500, 503] as $code) {
            $this->assertTrue(Delivery::is_retryable($code), (string) $code);
        }
        foreach ([200, 400, 401, 403, 404, 422] as $code) {
            $this->assertFalse(Delivery::is_retryable($code), (string) $code);
        }
    }

    /**
     * A queue backed by the given select/execute recorders and rows.
     *
     * @param array<int, string>|null          $executed Filled with every executed SQL statement.
     * @param array<int, string>|null          $selected Filled with every select SQL statement.
     * @param array<int, array<string, mixed>> $rows     Rows due() returns.
     * @return Queue
     */
    private function queue(&$executed = null, &$selected = null, array $rows = []): Queue
    {
        $executed = [];
        $selected = [];
        return new Queue(
            static function (string $sql) use (&$selected, $rows): array {
                $selected[] = $sql;
                return $rows;
            },
            static function (string $sql) use (&$executed): bool {
                $executed[] = $sql;
                return true;
            },
            'ps_'
        );
    }

    /**
     * A due queue row.
     *
     * @param array<string, mixed> $changes Overrides.
     * @return array{id_queue: int, id_order: int, event: string, delivery_id: string, attempt: int}
     */
    private function row(array $changes = []): array
    {
        return array_merge([
            'id_queue' => 1,
            'id_order' => 7,
            'event' => 'order.status_changed',
            'delivery_id' => 'uuid-1',
            'attempt' => 0,
        ], $changes);
    }

    /**
     * Ready-to-send settings, with overrides.
     *
     * @param array<string, mixed> $changes Overrides.
     * @return array{url: string, secret: string, statuses: string[], active: bool, payload_format: string}
     */
    private function settings(array $changes = []): array
    {
        return array_merge([
            'url' => 'https://billmysales.example/hook',
            'secret' => 's3cret',
            'statuses' => ['2'],
            'active' => true,
            'payload_format' => Settings::FORMAT_LEGACY,
        ], $changes);
    }

    /**
     * @return array{source: string, platform_version: string, plugin_version: string}
     */
    private function context(): array
    {
        return ['source' => 'https://shop.example/', 'platform_version' => '9.1.5', 'plugin_version' => '2.0.0'];
    }

    /**
     * @return callable():string
     */
    private function fixed_now(): callable
    {
        return static fn (): string => '2026-01-01 10:00:00';
    }

    /**
     * @param string $uuid UUID to return.
     * @return callable():string
     */
    private function fixed_uuid(string $uuid = 'uuid-1'): callable
    {
        return static fn (): string => $uuid;
    }

    /**
     * A callable that fails the test if it is ever called.
     *
     * @return callable
     */
    private function never_called(): callable
    {
        return function () {
            $this->fail('This callable should not have been called.');
        };
    }

    /**
     * A callable recording every on_result() call into $results.
     *
     * @param array<int, array<int, mixed>> $results Filled with each call's arguments.
     * @return callable(int, string, string, string, string, int): void
     */
    private function record_result(array &$results): callable
    {
        return static function (int $id_order, string $event, string $delivery_id, string $result, string $detail, int $attempt) use (&$results): void {
            $results[] = [$id_order, $event, $delivery_id, $result, $detail, $attempt];
        };
    }
}

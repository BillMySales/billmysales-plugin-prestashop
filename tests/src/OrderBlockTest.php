<?php

declare(strict_types=1);

/**
 * Tests of the order detail page block's view model.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Admin\OrderBlock;
use BillMySales\PrestaShop\Webhook\Delivery;

/**
 * @covers \BillMySales\PrestaShop\Admin\OrderBlock
 */
final class OrderBlockTest extends TestCase
{
    /**
     * Fields carry their current value; delivered's label is translated;
     * "Send to BillMySales" is offered.
     *
     * @return void
     */
    public function test_view_with_a_delivered_status(): void
    {
        $fields = [
            ['key' => 'tax-id', 'label' => 'Tax id', 'values' => [], 'required' => true],
        ];
        $status = ['status' => Delivery::RESULT_DELIVERED, 'detail' => 'HTTP 200', 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempts' => 0, 'updated_at' => '2026-01-01 10:00:00'];

        $view = OrderBlock::view($this->uppercase(), $fields, ['tax-id' => '11.111.111-1'], $status, true);

        $this->assertSame([
            ['key' => 'tax-id', 'label' => 'Tax id', 'values' => [], 'required' => true, 'value' => '11.111.111-1'],
        ], $view['fields']);
        $this->assertSame(['label' => 'SENT', 'detail' => 'HTTP 200', 'updated_at' => '2026-01-01 10:00:00'], $view['delivery_status']);
        $this->assertTrue($view['can_resend']);
    }

    /**
     * A field without a saved value gets an empty one; no status yet is
     * null; resend can be withheld.
     *
     * @return void
     */
    public function test_view_with_no_status_yet(): void
    {
        $fields = [
            ['key' => 'tax-id', 'label' => 'Tax id', 'values' => [], 'required' => true],
        ];

        $view = OrderBlock::view($this->identity(), $fields, [], null, false);

        $this->assertSame('', $view['fields'][0]['value']);
        $this->assertNull($view['delivery_status']);
        $this->assertFalse($view['can_resend']);
    }

    /**
     * Every Delivery::RESULT_* status gets a translated label, except
     * "gone" (never persisted to the order status store).
     *
     * @return void
     */
    public function test_view_translates_every_status_label(): void
    {
        foreach ([Delivery::RESULT_RETRY, Delivery::RESULT_GIVEN_UP, Delivery::RESULT_REJECTED] as $status) {
            $view = OrderBlock::view(
                static fn (string $s): string => "[{$s}]",
                [],
                [],
                ['status' => $status, 'detail' => '', 'event' => '', 'delivery_id' => '', 'attempts' => 0, 'updated_at' => '2026-01-01 10:00:00'],
                false
            );
            $this->assertStringStartsWith('[', $view['delivery_status']['label']);
        }
    }

    /**
     * An unknown status (shouldn't happen, but defensively) falls back to
     * showing the raw status string.
     *
     * @return void
     */
    public function test_view_with_an_unknown_status(): void
    {
        $view = OrderBlock::view(
            $this->identity(),
            [],
            [],
            ['status' => 'something_else', 'detail' => '', 'event' => '', 'delivery_id' => '', 'attempts' => 0, 'updated_at' => '2026-01-01 10:00:00'],
            false
        );
        $this->assertSame('something_else', $view['delivery_status']['label']);
    }

    /**
     * @return callable(string):string
     */
    private function uppercase(): callable
    {
        return static fn (string $s): string => strtoupper($s);
    }

    /**
     * @return callable(string):string
     */
    private function identity(): callable
    {
        return static fn (string $s): string => $s;
    }
}

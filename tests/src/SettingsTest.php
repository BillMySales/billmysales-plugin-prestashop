<?php

declare(strict_types=1);

/**
 * Tests of the delivery settings.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Settings;

/**
 * @covers \BillMySales\PrestaShop\Settings
 */
final class SettingsTest extends TestCase
{
    /**
     * Raw Configuration values are shaped into the settings array.
     *
     * @return void
     */
    public function test_from_raw_with_valid_values(): void
    {
        $settings = Settings::from_raw('https://example.com/hook', 's3cret', '["2","4"]', '1', Settings::FORMAT_WEBSERVICE);
        $this->assertSame([
            'url' => 'https://example.com/hook',
            'secret' => 's3cret',
            'statuses' => ['2', '4'],
            'active' => true,
            'payload_format' => Settings::FORMAT_WEBSERVICE,
        ], $settings);
    }

    /**
     * Missing or invalid Configuration values (false, non-JSON, unknown
     * format) fall back to defaults.
     *
     * @return void
     */
    public function test_from_raw_falls_back_to_defaults(): void
    {
        $settings = Settings::from_raw(false, false, false, false, false);
        $this->assertSame(Settings::DEFAULTS, $settings);
    }

    /**
     * A statuses value that decodes to something other than an array (e.g.
     * a JSON scalar) also falls back to the default.
     *
     * @return void
     */
    public function test_from_raw_with_non_array_statuses(): void
    {
        $settings = Settings::from_raw('', '', '"not-an-array"', '', '');
        $this->assertSame([], $settings['statuses']);
    }

    /**
     * An unknown payload format falls back to the default.
     *
     * @return void
     */
    public function test_from_raw_with_unknown_payload_format(): void
    {
        $settings = Settings::from_raw('', '', '[]', '', 'unknown');
        $this->assertSame(Settings::FORMAT_LEGACY, $settings['payload_format']);
    }

    /**
     * Ready only when active, with a URL and a secret.
     *
     * @return void
     */
    public function test_is_ready(): void
    {
        $this->assertTrue(Settings::is_ready($this->settings()));
        $this->assertFalse(Settings::is_ready($this->settings(['active' => false])));
        $this->assertFalse(Settings::is_ready($this->settings(['url' => ''])));
        $this->assertFalse(Settings::is_ready($this->settings(['secret' => ''])));
    }

    /**
     * Notifies only when ready and the state is selected.
     *
     * @return void
     */
    public function test_notifies(): void
    {
        $settings = $this->settings(['statuses' => ['2']]);
        $this->assertTrue(Settings::notifies($settings, 2));
        $this->assertFalse(Settings::notifies($settings, 3));
        $this->assertFalse(Settings::notifies($this->settings(['statuses' => ['2'], 'active' => false]), 2));
    }

    /**
     * The settings form is sanitized: trimmed URL, typed secret, only known
     * order states selected (in their original order), a known payload
     * format.
     *
     * @return void
     */
    public function test_sanitize_with_valid_input(): void
    {
        $input = [
            Settings::KEY_URL => '  https://example.com/hook  ',
            Settings::KEY_SECRET => 's3cret',
            Settings::KEY_ACTIVE => '1',
            Settings::KEY_STATUSES . '_2' => '1',
            Settings::KEY_STATUSES . '_3' => '0',
            Settings::KEY_STATUSES . '_4' => '1',
            Settings::KEY_PAYLOAD_FORMAT => Settings::FORMAT_WEBSERVICE,
        ];
        $settings = Settings::sanitize($input, [2, 3, 4]);
        $this->assertSame([
            'url' => 'https://example.com/hook',
            'secret' => 's3cret',
            'statuses' => ['2', '4'],
            'active' => true,
            'payload_format' => Settings::FORMAT_WEBSERVICE,
        ], $settings);
    }

    /**
     * Missing form fields (URL, secret, active, payload format) sanitize to
     * empty/false/default values.
     *
     * @return void
     */
    public function test_sanitize_with_missing_input(): void
    {
        $settings = Settings::sanitize([], [2]);
        $this->assertSame([
            'url' => '',
            'secret' => '',
            'statuses' => [],
            'active' => false,
            'payload_format' => Settings::FORMAT_LEGACY,
        ], $settings);
    }

    /**
     * A status id not in the valid list is dropped even if posted as
     * selected (tampered request).
     *
     * @return void
     */
    public function test_sanitize_drops_unknown_statuses(): void
    {
        $settings = Settings::sanitize([Settings::KEY_STATUSES . '_99' => '1'], [2]);
        $this->assertSame([], $settings['statuses']);
    }

    /**
     * An unknown posted payload format falls back to the default.
     *
     * @return void
     */
    public function test_sanitize_with_unknown_payload_format(): void
    {
        $settings = Settings::sanitize([Settings::KEY_PAYLOAD_FORMAT => 'unknown'], []);
        $this->assertSame(Settings::FORMAT_LEGACY, $settings['payload_format']);
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
            'url' => 'https://example.com/hook',
            'secret' => 's3cret',
            'statuses' => ['2'],
            'active' => true,
            'payload_format' => Settings::FORMAT_LEGACY,
        ], $changes);
    }
}

<?php

declare(strict_types=1);

/**
 * Delivery settings: endpoint URL, secret, order states that notify.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop;

/**
 * Reads, validates and stores the delivery settings. Storage itself
 * (Configuration::get()/updateValue()) is left to the caller (the module),
 * so this class never touches PrestaShop's runtime directly and stays
 * unit-testable: it only shapes and validates plain arrays.
 */
final class Settings
{
    /**
     * Configuration key of the "active" switch.
     */
    public const KEY_ACTIVE = 'BILLMYSALES_ACTIVE';

    /**
     * Configuration key of the notification URL.
     */
    public const KEY_URL = 'BILLMYSALES_WEBHOOK';

    /**
     * Configuration key of the shared secret.
     */
    public const KEY_SECRET = 'BILLMYSALES_TOKEN';

    /**
     * Configuration key of the order states that notify (JSON array of
     * order state ids, as strings).
     */
    public const KEY_STATUSES = 'BILLMYSALES_NOTIFY_STATUSES';

    /**
     * Configuration key of the payload format.
     */
    public const KEY_PAYLOAD_FORMAT = 'BILLMYSALES_PAYLOAD_FORMAT';

    /**
     * Payload format kept for compatibility with the deliveries BillMySales
     * already parses.
     */
    public const FORMAT_LEGACY = 'legacy';

    /**
     * Payload format built from PrestaShop's own webservice representation
     * of the order.
     */
    public const FORMAT_WEBSERVICE = 'webservice';

    /**
     * Valid payload formats.
     */
    public const PAYLOAD_FORMATS = [self::FORMAT_LEGACY, self::FORMAT_WEBSERVICE];

    /**
     * Default values.
     *
     * @var array{url: string, secret: string, statuses: string[], active: bool, payload_format: string}
     */
    public const DEFAULTS = [
        'url' => '',
        'secret' => '',
        'statuses' => [],
        'active' => false,
        'payload_format' => self::FORMAT_LEGACY,
    ];

    /**
     * Builds the settings array from raw Configuration values.
     *
     * @param string|false $url            Raw BILLMYSALES_WEBHOOK value.
     * @param string|false $secret         Raw BILLMYSALES_TOKEN value.
     * @param string|false $statuses       Raw BILLMYSALES_NOTIFY_STATUSES value (JSON).
     * @param string|false $active         Raw BILLMYSALES_ACTIVE value.
     * @param string|false $payload_format Raw BILLMYSALES_PAYLOAD_FORMAT value.
     * @return array{url: string, secret: string, statuses: string[], active: bool, payload_format: string}
     */
    public static function from_raw($url, $secret, $statuses, $active, $payload_format): array
    {
        $decoded = json_decode(is_string($statuses) ? $statuses : '', true);
        return [
            'url' => is_string($url) ? $url : self::DEFAULTS['url'],
            'secret' => is_string($secret) ? $secret : self::DEFAULTS['secret'],
            'statuses' => is_array($decoded) ? array_map('strval', $decoded) : self::DEFAULTS['statuses'],
            'active' => (bool) $active,
            'payload_format' => in_array($payload_format, self::PAYLOAD_FORMATS, true) ? $payload_format : self::DEFAULTS['payload_format'],
        ];
    }

    /**
     * Whether deliveries are enabled and fully configured.
     *
     * @param array{url: string, secret: string, statuses: string[], active: bool, payload_format: string} $settings Settings.
     * @return bool
     */
    public static function is_ready(array $settings): bool
    {
        return $settings['active'] && '' !== $settings['url'] && '' !== $settings['secret'];
    }

    /**
     * Whether an order state notifies, given the settings.
     *
     * @param array{url: string, secret: string, statuses: string[], active: bool, payload_format: string} $settings       Settings.
     * @param int                                                                                           $id_order_state Order state id.
     * @return bool
     */
    public static function notifies(array $settings, int $id_order_state): bool
    {
        return self::is_ready($settings) && in_array((string) $id_order_state, $settings['statuses'], true);
    }

    /**
     * Validates the settings form, given the valid order state ids.
     *
     * @param array<string, mixed> $input          Raw form data (already unslashed).
     * @param int[]                $valid_statuses Every existing order state id.
     * @return array{url: string, secret: string, statuses: string[], active: bool, payload_format: string}
     */
    public static function sanitize(array $input, array $valid_statuses): array
    {
        $url = isset($input[self::KEY_URL]) ? trim((string) $input[self::KEY_URL]) : '';
        // Stored as typed: it must match the secret in BillMySales.
        $secret = isset($input[self::KEY_SECRET]) ? (string) $input[self::KEY_SECRET] : '';

        $valid = array_map('strval', $valid_statuses);
        $statuses = [];
        foreach ($valid_statuses as $id_order_state) {
            if (!empty($input[self::KEY_STATUSES . '_' . $id_order_state])) {
                $statuses[] = (string) $id_order_state;
            }
        }
        $statuses = array_values(array_intersect($statuses, $valid));

        $payload_format = isset($input[self::KEY_PAYLOAD_FORMAT]) ? (string) $input[self::KEY_PAYLOAD_FORMAT] : self::DEFAULTS['payload_format'];

        return [
            'url' => $url,
            'secret' => $secret,
            'statuses' => $statuses,
            'active' => !empty($input[self::KEY_ACTIVE]),
            'payload_format' => in_array($payload_format, self::PAYLOAD_FORMATS, true) ? $payload_format : self::DEFAULTS['payload_format'],
        ];
    }
}

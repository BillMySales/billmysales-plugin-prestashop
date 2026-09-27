<?php

declare(strict_types=1);

/**
 * Installation and removal: hooks, default settings, database schema.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\PrestaShop;

use BillMySales\PrestaShop\Checkout\CheckoutFields;

/**
 * Pure data and SQL builders for install()/uninstall(): registering hooks
 * and running SQL is left to the caller (the module), so this class stays
 * unit-testable.
 */
final class Installer
{
    /**
     * Hooks the module registers.
     *
     * @var string[]
     */
    public const HOOKS = [
        'displayBackOfficeHeader',
        'additionalCustomerAddressFields',
        'actionObjectAddressAddAfter',
        'actionObjectAddressUpdateAfter',
        'actionObjectAddressDeleteAfter',
        'actionOrderStatusPostUpdate',
        'displayAdminOrderMainBottom',
        'displayAdminOrderCreateFields',
        'actionCronJob',
    ];

    /**
     * Default Configuration values.
     *
     * @var array<string, string>
     */
    public const DEFAULT_CONFIG = [
        Settings::KEY_ACTIVE => '',
        Settings::KEY_URL => '',
        Settings::KEY_SECRET => '',
        Settings::KEY_STATUSES => '[]',
        Settings::KEY_PAYLOAD_FORMAT => Settings::FORMAT_LEGACY,
        CheckoutFields::KEY => '[]',
    ];

    /**
     * Statements that create the module's tables.
     *
     * @param string $db_prefix    The shop's table prefix.
     * @param string $mysql_engine Database engine (e.g. InnoDB).
     * @return string[]
     */
    public static function install_sql(string $db_prefix, string $mysql_engine): array
    {
        return [
            sprintf(
                "CREATE TABLE IF NOT EXISTS `%sbillmysales_address_field` (\n"
                    . "    `id_address` INT UNSIGNED NOT NULL,\n"
                    . "    `field_key` VARCHAR(64) NOT NULL,\n"
                    . "    `field_value` VARCHAR(255) NOT NULL DEFAULT '',\n"
                    . "    PRIMARY KEY (`id_address`, `field_key`)\n"
                    . ') ENGINE=%s DEFAULT CHARSET=utf8mb4;',
                $db_prefix,
                $mysql_engine
            ),
            sprintf(
                "CREATE TABLE IF NOT EXISTS `%sbillmysales_delivery_queue` (\n"
                    . "    `id_queue` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
                    . "    `id_order` INT UNSIGNED NOT NULL,\n"
                    . "    `event` VARCHAR(32) NOT NULL,\n"
                    . "    `delivery_id` VARCHAR(36) NOT NULL,\n"
                    . "    `attempt` INT UNSIGNED NOT NULL DEFAULT 0,\n"
                    . "    `next_attempt_at` DATETIME NOT NULL,\n"
                    . "    `created_at` DATETIME NOT NULL,\n"
                    . "    PRIMARY KEY (`id_queue`),\n"
                    . "    KEY `next_attempt_at` (`next_attempt_at`)\n"
                    . ') ENGINE=%s DEFAULT CHARSET=utf8mb4;',
                $db_prefix,
                $mysql_engine
            ),
            sprintf(
                "CREATE TABLE IF NOT EXISTS `%sbillmysales_order_status` (\n"
                    . "    `id_order` INT UNSIGNED NOT NULL,\n"
                    . "    `status` VARCHAR(16) NOT NULL,\n"
                    . "    `detail` VARCHAR(255) NOT NULL DEFAULT '',\n"
                    . "    `event` VARCHAR(32) NOT NULL DEFAULT '',\n"
                    . "    `delivery_id` VARCHAR(36) NOT NULL DEFAULT '',\n"
                    . "    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,\n"
                    . "    `updated_at` DATETIME NOT NULL,\n"
                    . "    PRIMARY KEY (`id_order`)\n"
                    . ') ENGINE=%s DEFAULT CHARSET=utf8mb4;',
                $db_prefix,
                $mysql_engine
            ),
        ];
    }

    /**
     * Statements that drop the module's tables.
     *
     * @param string $db_prefix The shop's table prefix.
     * @return string[]
     */
    public static function uninstall_sql(string $db_prefix): array
    {
        return [
            sprintf('DROP TABLE IF EXISTS `%sbillmysales_address_field`;', $db_prefix),
            sprintf('DROP TABLE IF EXISTS `%sbillmysales_delivery_queue`;', $db_prefix),
            sprintf('DROP TABLE IF EXISTS `%sbillmysales_order_status`;', $db_prefix),
        ];
    }
}

<?php

declare(strict_types=1);

/**
 * BillMySales for PrestaShop.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the European Union Public Licence v. 1.2 (EUPL-1.2).
 * See LICENSE file for more details.
 */

/**
 * End-to-end tests: a small CLI, bootstrapping PrestaShop itself, for the
 * setup steps the platform has no official CLI for (creating a product,
 * changing an order's state through its real API so
 * actionOrderStatusPostUpdate fires). Run inside the stack's "prestashop"
 * container (tests/e2e/run.sh copies this file there first). Prints one
 * plain-text result line per command and exits non-zero on failure.
 *
 * Usage: php e2e-fixtures.php <command> [args...]
 */

require_once '/var/www/html/config/config.inc.php';

// The front bootstrap alone leaves Context without a currency/language/
// country (normally set by the request Dispatcher): Order::setCurrentState()
// needs them to compute the order's invoice.
$context = Context::getContext();
$context->shop = $context->shop ?: new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
$context->currency = $context->currency ?: new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
$context->language = $context->language ?: new Language((int) Configuration::get('PS_LANG_DEFAULT'));
$context->country = $context->country ?: new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'));

/**
 * Creates an active, in-stock product with no tax, in the default category.
 *
 * @param string $name  Product name.
 * @param string $price Price, tax excluded.
 * @return void
 */
function billmysales_e2e_create_product(string $name, string $price): void
{
    $product = new Product();
    $product->name = [(int) Configuration::get('PS_LANG_DEFAULT') => $name];
    $product->id_category_default = (int) Configuration::get('PS_HOME_CATEGORY');
    $product->id_tax_rules_group = 0;
    $product->price = (float) $price;
    $product->active = true;
    $product->indexed = false;
    $product->show_price = true;
    $product->available_for_order = true;
    $product->add();
    $product->addToCategories([(int) Configuration::get('PS_HOME_CATEGORY')]);
    StockAvailable::setQuantity((int) $product->id, 0, 100);
    echo $product->id . "\n";
}

/**
 * Changes an order's state through Order::setCurrentState(), so the
 * plugin's actionOrderStatusPostUpdate hook fires exactly as it would from
 * the back office.
 *
 * @param string $id_order       Order id.
 * @param string $id_order_state Target order state id.
 * @return void
 */
function billmysales_e2e_set_order_state(string $id_order, string $id_order_state): void
{
    $order = new Order((int) $id_order);
    if (!Validate::isLoadedObject($order)) {
        fwrite(STDERR, "order {$id_order} not found\n");
        exit(1);
    }
    try {
        $order->setCurrentState((int) $id_order_state);
    } catch (\Throwable $e) {
        // changeIdOrderState() already ran (hooks fired, state persisted)
        // before this: the customer notification email is the only step
        // that needs the Symfony kernel, which a CLI bootstrap doesn't
        // have. Nothing left to do but ignore it.
    }
    echo "ok\n";
}

/**
 * Prints one field of an order (as PHP's Order object holds it, e.g.
 * total_paid, current_state).
 *
 * @param string $id_order Order id.
 * @param string $field    Property name.
 * @return void
 */
function billmysales_e2e_get_order_field(string $id_order, string $field): void
{
    $order = new Order((int) $id_order);
    if (!Validate::isLoadedObject($order)) {
        fwrite(STDERR, "order {$id_order} not found\n");
        exit(1);
    }
    echo $order->{$field} . "\n";
}

/**
 * Sets a Configuration value (the module's settings and checkout fields are
 * plain Configuration values; see Settings and CheckoutFields).
 *
 * @param string $key   Configuration key.
 * @param string $value Raw value.
 * @return void
 */
function billmysales_e2e_set_config(string $key, string $value): void
{
    Configuration::updateValue($key, $value);
    echo "ok\n";
}

/**
 * Prints a Configuration value.
 *
 * @param string $key Configuration key.
 * @return void
 */
function billmysales_e2e_get_config(string $key): void
{
    echo Configuration::get($key) . "\n";
}

/**
 * Prints a country's id from its ISO code (e.g. "CL").
 *
 * @param string $iso_code ISO 3166-1 alpha-2 code.
 * @return void
 */
function billmysales_e2e_country_id(string $iso_code): void
{
    echo (int) Country::getByIso($iso_code) . "\n";
}

/**
 * Prints how many of the module's own tables still exist (0 once
 * uninstalled).
 *
 * @return void
 */
function billmysales_e2e_count_tables(): void
{
    $count = 0;
    foreach (['billmysales_address_field', 'billmysales_delivery_queue', 'billmysales_order_status'] as $table) {
        if (Db::getInstance()->getRow('SHOW TABLES LIKE "' . _DB_PREFIX_ . $table . '"')) {
            ++$count;
        }
    }
    echo $count . "\n";
}

/**
 * Prints how many BILLMYSALES_* Configuration rows still exist (0 once
 * uninstalled).
 *
 * @return void
 */
function billmysales_e2e_count_config(): void
{
    $row = Db::getInstance()->getRow('SELECT COUNT(*) AS c FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` LIKE "BILLMYSALES%"');
    echo (int) ($row['c'] ?? 0) . "\n";
}

/**
 * Makes a queued delivery due right now, instead of waiting for its retry
 * delay to elapse.
 *
 * @param string $id_order Order id.
 * @return void
 */
function billmysales_e2e_force_retry_now(string $id_order): void
{
    // PHP's own now (not SQL's NOW()): the queue compares next_attempt_at
    // against a PHP-generated timestamp, and the database server's
    // timezone isn't necessarily the same as PHP's.
    Db::getInstance()->execute(sprintf(
        'UPDATE `%sbillmysales_delivery_queue` SET `next_attempt_at` = "%s" WHERE `id_order` = %d',
        _DB_PREFIX_,
        date('Y-m-d H:i:s'),
        (int) $id_order
    ));
    echo "ok\n";
}

$argv = $_SERVER['argv'] ?? [];
$command = $argv[1] ?? '';
$args = array_slice($argv, 2);

switch ($command) {
    case 'create-product':
        billmysales_e2e_create_product($args[0] ?? 'E2E product', $args[1] ?? '9990');
        break;
    case 'set-order-state':
        billmysales_e2e_set_order_state($args[0] ?? '0', $args[1] ?? '0');
        break;
    case 'get-order-field':
        billmysales_e2e_get_order_field($args[0] ?? '0', $args[1] ?? 'current_state');
        break;
    case 'set-config':
        billmysales_e2e_set_config($args[0] ?? '', $args[1] ?? '');
        break;
    case 'get-config':
        billmysales_e2e_get_config($args[0] ?? '');
        break;
    case 'country-id':
        billmysales_e2e_country_id($args[0] ?? '');
        break;
    case 'count-tables':
        billmysales_e2e_count_tables();
        break;
    case 'count-config':
        billmysales_e2e_count_config();
        break;
    case 'force-retry-now':
        billmysales_e2e_force_retry_now($args[0] ?? '0');
        break;
    default:
        fwrite(STDERR, "unknown command: {$command}\n");
        exit(1);
}

<?php

declare(strict_types=1);

/**
 * Tests of the installation SQL builders.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

use BillMySales\PrestaShop\Installer;

/**
 * @covers \BillMySales\PrestaShop\Installer
 */
final class InstallerTest extends TestCase
{
    /**
     * Every install statement carries the shop's prefix and engine, and
     * creates one of the module's three tables.
     *
     * @return void
     */
    public function test_install_sql(): void
    {
        $statements = Installer::install_sql('ps_', 'InnoDB');
        $this->assertCount(3, $statements);
        foreach (['ps_billmysales_address_field', 'ps_billmysales_delivery_queue', 'ps_billmysales_order_status'] as $table) {
            $matching = array_filter($statements, static fn (string $sql): bool => false !== strpos($sql, "CREATE TABLE IF NOT EXISTS `{$table}`"));
            $this->assertCount(1, $matching, "missing CREATE TABLE for {$table}");
        }
        foreach ($statements as $sql) {
            $this->assertStringContainsString('ENGINE=InnoDB', $sql);
        }
    }

    /**
     * Every uninstall statement drops one of the module's three tables,
     * with the shop's prefix.
     *
     * @return void
     */
    public function test_uninstall_sql(): void
    {
        $statements = Installer::uninstall_sql('ps_');
        $this->assertSame([
            'DROP TABLE IF EXISTS `ps_billmysales_address_field`;',
            'DROP TABLE IF EXISTS `ps_billmysales_delivery_queue`;',
            'DROP TABLE IF EXISTS `ps_billmysales_order_status`;',
        ], $statements);
    }
}

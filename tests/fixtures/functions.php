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
 * A minimal stand-in for PrestaShop's own pSQL(): the only global function
 * plugin/src calls directly (Queue and OrderStatus, escaping values before
 * building SQL). Good enough for unit tests, which never hit a real
 * database; PHPStan uses the real stubs instead (this file is excluded
 * there).
 */

if (!function_exists('pSQL')) {
    /**
     * @param string $string String to escape.
     * @param bool   $html   Unused (kept for signature compatibility).
     * @return string
     */
    function pSQL(string $string, bool $html = false): string
    {
        return addslashes($string);
    }
}

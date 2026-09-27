<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap. plugin/src is framework-free (it never references a
 * PrestaShop class, only receiving plain data and callables from the
 * module) and has no file-level side effects, so classes are simply
 * autoloaded (Composer's PSR-4); only `pSQL()`, the one global function
 * Queue and OrderStatus call directly, needs a stand-in.
 *
 * @package BillMySales\PrestaShop
 */

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/fixtures/functions.php';

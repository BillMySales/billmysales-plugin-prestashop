<?php

declare(strict_types=1);

/**
 * Base test case.
 *
 * @package BillMySales\PrestaShop
 */

namespace BillMySales\TestsPrestaShop;

/**
 * Every plugin/src class only receives plain data and callables (never a
 * PrestaShop class), so plain closures stand in for collaborators: no
 * mocking library is needed.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
}

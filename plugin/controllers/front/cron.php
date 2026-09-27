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
 * Sends the due deliveries when called with a valid token: the shop's
 * recommended way to run them on a schedule, from a system cron pointed at
 * this controller's URL (no dependency on PrestaShop's own, optional cron
 * job feature).
 *
 * @package BillMySales\PrestaShop
 */

/**
 * The module's own front controller (index.php?fc=module&module=billmysales&controller=cron&token=...).
 */
class BillmysalesCronModuleFrontController extends ModuleFrontController
{
    /**
     * No customer session is needed.
     *
     * @var bool
     */
    public $auth = false;

    /**
     * Runs the due deliveries and answers plain text.
     *
     * @return void
     */
    public function initContent()
    {
        header('Content-Type: text/plain; charset=utf-8');
        /** @var Billmysales $module */
        $module = $this->module;
        $token = (string) Tools::getValue('token');
        $expected = $module->cronToken();
        if ('' === $expected || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo 'forbidden';
            exit;
        }
        $module->processDueDeliveries();
        echo 'ok';
        // No template is set: this is a plain-text endpoint, not a page.
        // Exiting here skips the parent controller's normal render step,
        // which otherwise fails looking for one.
        exit;
    }
}

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
 * The module's own entry point: PrestaShop's hook dispatcher calls
 * hookXxx() directly on this instance, so it can't be a thin bootstrapper
 * the way the rest of the plugin is. Every hook method stays a short
 * adapter that turns PrestaShop's objects into plain data and delegates the
 * actual logic to the unit-tested classes in src/.
 *
 * @package BillMySales\PrestaShop
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

spl_autoload_register(
    static function (string $class_name): void {
        $prefix = 'BillMySales\\PrestaShop\\';
        if (0 !== strpos($class_name, $prefix)) {
            return;
        }
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class_name, strlen($prefix))) . '.php';
        if (is_readable($path)) {
            require $path;
        }
    }
);

use BillMySales\PrestaShop\Admin\OrderBlock;
use BillMySales\PrestaShop\Admin\SettingsPage;
use BillMySales\PrestaShop\Checkout\CheckoutFields;
use BillMySales\PrestaShop\Installer;
use BillMySales\PrestaShop\Settings;
use BillMySales\PrestaShop\Webhook\Delivery;
use BillMySales\PrestaShop\Webhook\OrderStatus;
use BillMySales\PrestaShop\Webhook\Queue;

/**
 * Sends PrestaShop orders to BillMySales when they reach the selected order
 * states, and adds custom fields to the checkout's address form.
 */
class Billmysales extends Module
{
    /**
     * Configuration key of the cron controller's token.
     */
    private const KEY_CRON_TOKEN = 'BILLMYSALES_CRON_TOKEN';

    /**
     * Maximum deliveries sent per opportunistic run (back office page load).
     */
    private const OPPORTUNISTIC_LIMIT = 5;

    /**
     * Maximum deliveries sent per cron run (the module's own controller, or
     * PrestaShop's cron job hook).
     */
    private const CRON_LIMIT = 50;

    /**
     * Sets the module's metadata.
     */
    public function __construct()
    {
        $this->name = 'billmysales';
        $this->tab = 'billing_invoicing';
        $this->version = '2.0.0';
        $this->author = 'BillMySales';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.2', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('BillMySales');
        $this->description = $this->l('Sends orders to BillMySales when they reach the selected order states, and adds custom billing fields (e.g. tax id, business activity) to the checkout.');
        $this->confirmUninstall = $this->l('This will stop automatic billing through BillMySales.');

        $warnings = [];
        if (!Configuration::get(Settings::KEY_ACTIVE)) {
            $warnings[] = $this->l('Deliveries to BillMySales are turned off.');
        }
        if (!Configuration::get(Settings::KEY_URL)) {
            $warnings[] = $this->l('The BillMySales notification URL is not set.');
        }
        $this->warning = implode(' ', $warnings);
    }

    /**
     * Installs the module: default settings, database tables, hooks, cron
     * token.
     *
     * @return bool
     */
    public function install()
    {
        foreach (Installer::DEFAULT_CONFIG as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }
        if (!Configuration::get(self::KEY_CRON_TOKEN)) {
            Configuration::updateValue(self::KEY_CRON_TOKEN, bin2hex(random_bytes(20)));
        }

        foreach (Installer::install_sql(_DB_PREFIX_, _MYSQL_ENGINE_) as $sql) {
            if (false === Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        if (!parent::install()) {
            return false;
        }
        foreach (Installer::HOOKS as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Uninstalls the module: settings, database tables.
     *
     * @return bool
     */
    public function uninstall()
    {
        foreach (array_keys(Installer::DEFAULT_CONFIG) as $key) {
            Configuration::deleteByName($key);
        }
        Configuration::deleteByName(self::KEY_CRON_TOKEN);

        foreach (Installer::uninstall_sql(_DB_PREFIX_) as $sql) {
            if (false === Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return parent::uninstall();
    }

    /**
     * Renders the settings page (Modules > BillMySales > Configure).
     *
     * @return string
     */
    public function getContent()
    {
        if (Tools::isSubmit('submitBillMySalesSettings')) {
            $this->save_settings();
        }
        if (Tools::isSubmit('submitBillMySalesCustomFields')) {
            $this->save_checkout_fields();
        }

        $tab = 'fields' === Tools::getValue('billmysales_tab') ? 'fields' : 'settings';
        $tab_url = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules');

        $this->context->smarty->assign([
            'billmysales_tab' => $tab,
            'billmysales_tab_url' => $tab_url,
        ]);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        if ('fields' === $tab) {
            $this->context->smarty->assign('billmysales_fields', SettingsPage::fields_view($this->checkout_fields()));
            $this->context->smarty->assign([
                'billmysales_current_index' => $tab_url,
            ]);
            $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/custom_fields.tpl');
        } else {
            $output .= $this->render_settings_form();
        }

        return $output;
    }

    /**
     * Renders the "Settings" tab's form (HelperForm).
     *
     * @return string
     */
    private function render_settings_form(): string
    {
        $order_states = OrderState::getOrderStates((int) $this->context->language->id);
        $settings = $this->settings();

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBillMySalesSettings';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => SettingsPage::form_values($settings, $order_states),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([SettingsPage::form_definition([$this, 'l'], $order_states)]);
    }

    /**
     * Adds the CSS and JavaScript of the settings page and the order pages'
     * blocks; also runs a small, bounded batch of due deliveries, so a shop
     * with no cron configured still delivers eventually.
     *
     * @return void
     */
    public function hookDisplayBackOfficeHeader()
    {
        if ($this->is_own_configure_page()) {
            $this->context->controller->addJS($this->_path . 'assets/js/admin.js');
            $this->context->controller->addCSS($this->_path . 'assets/css/admin.css');
        }
        $this->process_due_batch(self::OPPORTUNISTIC_LIMIT);
    }

    /**
     * Adds the configured custom fields to the address form (checkout and
     * "My addresses").
     *
     * @param array<string, mixed> $params Hook parameters (unused).
     * @return FormField[]
     */
    public function hookAdditionalCustomerAddressFields(array $params = [])
    {
        unset($params);
        $extra_fields = [];
        foreach ($this->checkout_fields() as $field) {
            $form_field = new FormField();
            $form_field->setName('billmysales_' . $field['key'])
                ->setLabel($field['label'])
                ->setRequired($field['required']);
            if ([] !== $field['values']) {
                $form_field->setType('select');
                foreach ($field['values'] as $value) {
                    $form_field->addAvailableValue($value, $value);
                }
            } else {
                $form_field->setType('text');
            }
            $extra_fields[] = $form_field;
        }
        return $extra_fields;
    }

    /**
     * Saves the custom field values posted with a new address.
     *
     * @param array<string, mixed> $params Hook parameters ("object": the address).
     * @return void
     */
    public function hookActionObjectAddressAddAfter(array $params = [])
    {
        if (!empty($params['object']) && $params['object'] instanceof Address) {
            $this->save_address_custom_fields($params['object']);
        }
    }

    /**
     * Saves the custom field values posted with an updated address.
     *
     * @param array<string, mixed> $params Hook parameters ("object": the address).
     * @return void
     */
    public function hookActionObjectAddressUpdateAfter(array $params = [])
    {
        if (!empty($params['object']) && $params['object'] instanceof Address) {
            $this->save_address_custom_fields($params['object']);
        }
    }

    /**
     * Removes the custom field values of a deleted address.
     *
     * @param array<string, mixed> $params Hook parameters ("object": the address).
     * @return void
     */
    public function hookActionObjectAddressDeleteAfter(array $params = [])
    {
        if (empty($params['object']) || !($params['object'] instanceof Address)) {
            return;
        }
        Db::getInstance()->execute(sprintf(
            'DELETE FROM `%sbillmysales_address_field` WHERE `id_address` = %d',
            _DB_PREFIX_,
            (int) $params['object']->id
        ));
    }

    /**
     * Queues a delivery when an order reaches a selected order state.
     *
     * @param array<string, mixed> $params Hook parameters ("newOrderStatus", "oldOrderStatus", "id_order").
     * @return void
     */
    public function hookActionOrderStatusPostUpdate(array $params = [])
    {
        if (empty($params['newOrderStatus']) || empty($params['id_order'])) {
            return;
        }

        // Saved unconditionally, before the order state filter: an order
        // created from "Add new order" with the fields in the same request
        // (see hookDisplayAdminOrderCreateFields()) must keep them even
        // when its first state doesn't notify.
        $order = new Order((int) $params['id_order']);
        $cart = new Cart((int) $order->id_cart);
        $billing = new Address((int) $cart->id_address_invoice);
        $this->save_address_custom_fields($billing);

        $id_order_state = (int) $params['newOrderStatus']->id;
        $settings = $this->settings();
        if (!Settings::notifies($settings, $id_order_state)) {
            return;
        }

        $fields = $this->checkout_fields();
        $values = $this->address_custom_field_values((int) $billing->id);
        $missing = CheckoutFields::missing_required($fields, $values);
        if ([] !== $missing) {
            PrestaShopLogger::addLog(sprintf(
                'BillMySales: order #%d was not sent, missing required field(s): %s. Complete them in the order and change its state again.',
                $order->id,
                implode(', ', $missing)
            ), 2);
            return;
        }

        $event = empty($params['oldOrderStatus']) || !($params['oldOrderStatus'] instanceof OrderState) || !$params['oldOrderStatus']->id
            ? 'order.created'
            : 'order.status_changed';
        $this->delivery()->enqueue((int) $order->id, $event);
    }

    /**
     * Renders the "Billing data" block on the order detail page: the
     * custom fields (editable) and the last known delivery status.
     * Self-submitting: this method also saves the fields and queues a
     * resend when its own form is posted.
     *
     * @param array<string, mixed> $params Hook parameters ("id_order").
     * @return string
     */
    public function hookDisplayAdminOrderMainBottom(array $params = [])
    {
        if (empty($params['id_order'])) {
            return '';
        }
        $order = new Order((int) $params['id_order']);
        if (!Validate::isLoadedObject($order) || !$order->id_address_invoice) {
            return '';
        }
        $billing = new Address((int) $order->id_address_invoice);
        if (!Validate::isLoadedObject($billing)) {
            return '';
        }

        if (Tools::isSubmit('submitBillMySalesOrderFields') && (int) Tools::getValue('billmysales_id_order') === (int) $order->id) {
            $this->save_address_custom_fields($billing);
        }
        $settings = $this->settings();
        if (Tools::isSubmit('submitBillMySalesResend') && (int) Tools::getValue('billmysales_id_order') === (int) $order->id && Settings::is_ready($settings)) {
            $this->delivery()->enqueue((int) $order->id, 'order.resent');
        }

        $view = OrderBlock::view(
            [$this, 'l'],
            $this->checkout_fields(),
            $this->address_custom_field_values((int) $billing->id),
            $this->order_status_store()->get((int) $order->id),
            Settings::is_ready($settings)
        );
        $this->context->smarty->assign('billmysales_order_id', $order->id);
        $this->context->smarty->assign('billmysales', $view);
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/order_block.tpl');
    }

    /**
     * Adds the custom fields to the "Add new order" form: the order
     * doesn't exist yet, so nothing is pre-filled; the posted values are
     * saved in hookActionOrderStatusPostUpdate() (the same request creates
     * the order and sets its first state).
     *
     * @param array<string, mixed> $params Hook parameters (unused).
     * @return string
     */
    public function hookDisplayAdminOrderCreateFields(array $params = [])
    {
        unset($params);
        $fields = $this->checkout_fields();
        if ([] === $fields) {
            return '';
        }
        $this->context->smarty->assign('billmysales_fields', $fields);
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/order_create_fields.tpl');
    }

    /**
     * PrestaShop's own cron job hook (only called when the "cronjobs"
     * module is installed and configured to call it): an additional,
     * best-effort way to run due deliveries. The module's own cron
     * controller (controllers/front/cron.php) doesn't depend on it.
     *
     * @param array<string, mixed> $params Hook parameters (unused).
     * @return void
     */
    public function hookActionCronJob(array $params = [])
    {
        unset($params);
        $this->process_due_batch(self::CRON_LIMIT);
    }

    /**
     * The cron controller's token (controllers/front/cron.php).
     *
     * @return string
     */
    public function cronToken()
    {
        return (string) Configuration::get(self::KEY_CRON_TOKEN);
    }

    /**
     * Sends the due deliveries (called by the module's cron controller,
     * PrestaShop's cron job hook, and opportunistically from the back
     * office).
     *
     * @param int $limit Maximum deliveries sent in this run.
     * @return void
     */
    public function processDueDeliveries($limit = self::CRON_LIMIT)
    {
        $this->process_due_batch($limit);
    }

    /**
     * @param int $limit Maximum deliveries sent in this run.
     * @return void
     */
    private function process_due_batch(int $limit): void
    {
        $settings = $this->settings();
        if (!Settings::is_ready($settings)) {
            return;
        }
        $context = [
            'source' => Tools::getShopDomainSsl(true, true),
            'platform_version' => _PS_VERSION_,
            'plugin_version' => $this->version,
        ];
        $order_status_store = $this->order_status_store();
        $this->delivery()->process_due(
            $settings,
            $context,
            [$this, 'legacy_order_data'],
            [$this, 'webservice_order_data'],
            [$this, 'checkout_fields_meta_for_order'],
            [$this, 'http_post'],
            function (int $id_order, string $event, string $delivery_id, string $result, string $detail, int $attempts) use ($order_status_store): void {
                $order_status_store->record($id_order, $result, $detail, $event, $delivery_id, $attempts, date('Y-m-d H:i:s'));
                $level = Delivery::RESULT_DELIVERED === $result ? 3 : 1;
                PrestaShopLogger::addLog(sprintf('BillMySales: order #%d %s (%s, %s): %s', $id_order, $result, $event, $delivery_id, $detail), $level);
            },
            $limit
        );
    }

    /**
     * Whether the current request is this module's own settings page:
     * legacy admin controllers put it in "module_name"/"configure", the
     * Symfony module manager route in the URL path instead
     * (.../configure/billmysales).
     *
     * @return bool
     */
    private function is_own_configure_page(): bool
    {
        if ($this->name === Tools::getValue('module_name') || $this->name === Tools::getValue('configure')) {
            return true;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return false !== strpos($uri, 'configure/' . $this->name);
    }

    /**
     * @return array{url: string, secret: string, statuses: string[], active: bool, payload_format: string}
     */
    private function settings(): array
    {
        return Settings::from_raw(
            Configuration::get(Settings::KEY_URL),
            Configuration::get(Settings::KEY_SECRET),
            Configuration::get(Settings::KEY_STATUSES),
            Configuration::get(Settings::KEY_ACTIVE),
            Configuration::get(Settings::KEY_PAYLOAD_FORMAT)
        );
    }

    /**
     * @return void
     */
    private function save_settings(): void
    {
        $order_states = array_column(OrderState::getOrderStates((int) $this->context->language->id), 'id_order_state');
        $input = [
            Settings::KEY_ACTIVE => Tools::getValue(Settings::KEY_ACTIVE),
            Settings::KEY_URL => Tools::getValue(Settings::KEY_URL),
            Settings::KEY_SECRET => Tools::getValue(Settings::KEY_SECRET),
            Settings::KEY_PAYLOAD_FORMAT => Tools::getValue(Settings::KEY_PAYLOAD_FORMAT),
        ];
        foreach ($order_states as $id_order_state) {
            $input[Settings::KEY_STATUSES . '_' . $id_order_state] = Tools::getValue(Settings::KEY_STATUSES . '_' . $id_order_state);
        }
        $settings = Settings::sanitize($input, array_map('intval', $order_states));

        Configuration::updateValue(Settings::KEY_ACTIVE, $settings['active']);
        Configuration::updateValue(Settings::KEY_URL, $settings['url']);
        Configuration::updateValue(Settings::KEY_SECRET, $settings['secret']);
        Configuration::updateValue(Settings::KEY_PAYLOAD_FORMAT, $settings['payload_format']);
        Configuration::updateValue(Settings::KEY_STATUSES, json_encode($settings['statuses']));
    }

    /**
     * @return array<int, array{key: string, label: string, values: string[], required: bool}>
     */
    private function checkout_fields(): array
    {
        return CheckoutFields::from_raw(Configuration::get(CheckoutFields::KEY));
    }

    /**
     * @return void
     */
    private function save_checkout_fields(): void
    {
        $rows = Tools::getValue('billmysales_fields');
        $fields = CheckoutFields::sanitize(is_array($rows) ? $rows : []);
        Configuration::updateValue(CheckoutFields::KEY, json_encode($fields));
    }

    /**
     * Saves the custom field values posted for an address (does nothing
     * for a field absent from this request, e.g. saved from the back
     * office order actions instead of the checkout).
     *
     * @param Address $address Address.
     * @return void
     */
    private function save_address_custom_fields(Address $address): void
    {
        $fields = $this->checkout_fields();
        if ([] === $fields || !$address->id) {
            return;
        }
        foreach ($fields as $field) {
            $value = Tools::getValue('billmysales_' . $field['key']);
            if (false === $value) {
                continue;
            }
            Db::getInstance()->execute(sprintf(
                "REPLACE INTO `%sbillmysales_address_field` (`id_address`, `field_key`, `field_value`) VALUES (%d, '%s', '%s')",
                _DB_PREFIX_,
                (int) $address->id,
                pSQL($field['key']),
                pSQL((string) $value)
            ));
        }
    }

    /**
     * @param int $id_address Address id.
     * @return array<string, string>
     */
    private function address_custom_field_values(int $id_address): array
    {
        if (!$id_address) {
            return [];
        }
        // Cache disabled: this table is written and read back within the
        // same request (and across requests soon after), and PrestaShop's
        // SQL cache is keyed only by the query string.
        $rows = Db::getInstance()->executeS(sprintf(
            'SELECT `field_key`, `field_value` FROM `%sbillmysales_address_field` WHERE `id_address` = %d',
            _DB_PREFIX_,
            $id_address
        ), true, false);
        $values = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $values[$row['field_key']] = $row['field_value'];
            }
        }
        return $values;
    }

    /**
     * @param int $id_order Order id.
     * @return array<string, string>
     */
    public function checkout_fields_meta_for_order(int $id_order): array
    {
        $order = new Order($id_order);
        if (!Validate::isLoadedObject($order)) {
            return [];
        }
        $cart = new Cart((int) $order->id_cart);
        $billing = new Address((int) $cart->id_address_invoice);
        if (!Validate::isLoadedObject($billing)) {
            return [];
        }
        return CheckoutFields::payload_meta($this->checkout_fields(), $this->address_custom_field_values((int) $billing->id));
    }

    /**
     * The "legacy" order data (Settings::FORMAT_LEGACY): the shape
     * BillMySales' PrestaShop datasource has parsed since the 1.x module.
     *
     * @param int $id_order Order id.
     * @return array<string, mixed>|null
     */
    public function legacy_order_data(int $id_order): ?array
    {
        $order = new Order($id_order);
        if (!Validate::isLoadedObject($order)) {
            return null;
        }
        $cart = new Cart((int) $order->id_cart);
        $customer = new Customer((int) $order->id_customer);
        $address = new Address((int) $cart->id_address_delivery);
        $billing = new Address((int) $cart->id_address_invoice);
        $carrier = new Carrier((int) $order->id_carrier);
        $shop = new Shop((int) $order->id_shop);

        $data = get_object_vars($order);
        $data['id_order'] = $order->id;
        $data['customer'] = get_object_vars($customer);
        $data['cart'] = get_object_vars($cart);
        $data['cart']['rules'] = $cart->getCartRules();
        $data['address'] = get_object_vars($address);
        $data['billing'] = get_object_vars($billing);
        $data['carrier'] = get_object_vars($carrier);
        $data['products'] = $order->getProducts();
        $data['detail'] = $order->getOrderDetailList();
        $data['shop'] = get_object_vars($shop);
        return $data;
    }

    /**
     * The "webservice" order data (Settings::FORMAT_WEBSERVICE):
     * PrestaShop's own webservice representation of the order, built
     * in-process (Order::getWebserviceParameters(), WebserviceOutputBuilder),
     * without an HTTP call or a webservice key.
     *
     * @param int $id_order Order id.
     * @return array<string, mixed>|null
     */
    public function webservice_order_data(int $id_order): ?array
    {
        $order = new Order($id_order);
        if (!Validate::isLoadedObject($order)) {
            return null;
        }
        // Mirrors WebserviceRequest::run(): a render object (the output
        // format), the object under the "empty" key (its class gives the
        // webservice parameters), full fields, VIEW_DETAILS. getContent()
        // (its $override default) calls the render's overrideContent(),
        // so it already returns the final JSON string.
        $builder = new WebserviceOutputBuilder('');
        $builder->setObjectRender(new WebserviceOutputJSON(Language::getIDs()));
        $content = $builder->getContent(
            ['empty' => $order, $order->id => $order],
            null,
            'full',
            0,
            WebserviceOutputBuilder::VIEW_DETAILS
        );
        $decoded = json_decode((string) $content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param string                 $url     Notification URL.
     * @param string                 $body    Request body.
     * @param array<string, string>  $headers Request headers.
     * @return array{int, string} HTTP status (-1: network error), and the response body or error message.
     */
    public function http_post(string $url, string $body, array $headers): array
    {
        $header_lines = [];
        foreach ($headers as $name => $value) {
            $header_lines[] = $name . ': ' . $value;
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $header_lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $response = curl_exec($curl);
        if (false === $response) {
            $error = curl_error($curl);
            curl_close($curl);
            return [-1, $error];
        }
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return [$code, (string) $response];
    }

    /**
     * @return Queue
     */
    private function queue(): Queue
    {
        return new Queue([$this, 'db_select'], [$this, 'db_execute'], _DB_PREFIX_);
    }

    /**
     * @return OrderStatus
     */
    private function order_status_store(): OrderStatus
    {
        return new OrderStatus([$this, 'db_select'], [$this, 'db_execute'], _DB_PREFIX_);
    }

    /**
     * @return Delivery
     */
    private function delivery(): Delivery
    {
        return new Delivery(
            $this->queue(),
            static fn (): string => date('Y-m-d H:i:s'),
            [$this, 'generate_uuid']
        );
    }

    /**
     * @param string $sql SQL SELECT statement.
     * @return array<int, array<string, mixed>>
     */
    public function db_select(string $sql): array
    {
        // Cache disabled: the queue and status tables change between
        // requests, and PrestaShop's SQL cache is keyed only by the query
        // string.
        $rows = Db::getInstance()->executeS($sql, true, false);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param string $sql SQL statement.
     * @return bool
     */
    public function db_execute(string $sql): bool
    {
        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     * A random UUID v4.
     *
     * @return string
     */
    public function generate_uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}

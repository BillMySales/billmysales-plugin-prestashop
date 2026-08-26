<?php

/**
 * BillMySales: Pasarela de Facturación
 * Copyright (C) SASCO SpA (https://sasco.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero de GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU para
 * obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Módulo de BillMySales para PrestaShop
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
 * @version 2020-05-24
 */
class Billmysales extends Module
{
    private $defaultConfig = [
        'BILLMYSALES_ACTIVE' => false,
        'BILLMYSALES_LOG' => false,
        'BILLMYSALES_WEBHOOK' => '',
        'BILLMYSALES_TOKEN' => '',
        'BILLMYSALES_CUSTOM_FIELDS' => '[]',
        'BILLMYSALES_NOTIFY_STATUSES' => '[]',
    ]; ///< Configuración inicial del módulo

    protected $config_form = false;

    public function __construct()
    {

        // configuración base del módulo
        $this->name = 'billmysales';
        $this->tab = 'billing_invoicing';
        $this->version = '1.0.2';
        $this->author = 'SASCO SpA';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        // módulo es compatible con bootstrap (PrestaShop 1.7)
        $this->bootstrap = true;

        // crear módulo llamando al constructor
        parent::__construct();

        // textos para el usuario que ve el módulo
        $this->displayName = $this->l('BillMySales');
        $this->description = $this->l('Automatiza tus facturas y preocúpate de vender. BillMySales se encarga de procesar los pedidos de la tienda y pasarlos a tu facturador favorito. Así te puedes enfocar en hacer crecer tu negocio.');
        $this->confirmUninstall = $this->l('Al quitar el módulo tu facturación dejará de ser automática.');

        // advertencias de configuración
        $this->warning = [];
        if (!Configuration::get('BILLMYSALES_ACTIVE')) {
            $this->warning[] = $this->l('La facturación automática está desactivada.');
        }
        if (!Configuration::get('BILLMYSALES_WEBHOOK')) {
            $this->warning[] = $this->l('Falta configurar el webhook de www.billmysales.com');
        }
        $this->warning = implode(' ', $this->warning);
    }

    /**
     * Método que instala el plugin de BillMySales
     */
    public function install()
    {

        // valores por defecto de la configuración
        foreach ($this->defaultConfig as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        // base de datos
        include(dirname(__FILE__).'/sql/install.php');

        // instalar módulo
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('additionalCustomerAddressFields') &&
            $this->registerHook('actionObjectAddressAddAfter') &&
            $this->registerHook('actionObjectAddressUpdateAfter') &&
            $this->registerHook('actionObjectAddressDeleteAfter') &&
            $this->registerHook('displayAdminOrderMainBottom') &&
            $this->registerHook('displayAdminOrderCreateFields');

    }

    /**
     * Método que desinstala el plugin de BillMySales
     */
    public function uninstall()
    {

        // valores por defecto de la configuración
        foreach ($this->defaultConfig as $key => $value) {
            if (!Configuration::deleteByName($key)) {
                return false;
            }
        }

        // base de datos
        include(dirname(__FILE__).'/sql/uninstall.php');

        // desinstalar módulo
        return parent::uninstall();

    }

    /**
     * Método que carga el formulario de configuración
     */
    public function getContent()
    {
        // procesar valores pasados en el formulario de configuración
        if (((bool)Tools::isSubmit('submitBillMySalesModule')) == true) {
            $this->postProcess();
        }
        // procesar valores pasados en el formulario de campos personalizados
        if (((bool)Tools::isSubmit('submitBillMySalesCustomFields')) == true) {
            $this->postProcessCustomFields();
        }
        // pestaña actualmente seleccionada (Configuración o Campos personalizados)
        $tab = Tools::getValue('billmysales_tab', 'configuracion');
        // OJO: getAdminLink(..., false) NO incluye el token de seguridad;
        // hay que agregarlo a mano o los links de las pestañas disparan
        // "INVALID SECURITY TOKEN" al hacer clic
        $tab_url = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name
            .'&token='.Tools::getAdminTokenLite('AdminModules');
        // armar página del formulario
        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'billmysales_tab' => $tab,
            'billmysales_tab_url' => $tab_url,
        ]);
        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        if ($tab === 'campos') {
            $output .= $this->renderCustomFieldsForm();
        } else {
            $output .= $this->renderForm();
        }
        return $output.$this->footer();
    }

    /**
     * Método que genera el footer de la página de configuración del módulo
     */
    private function footer()
    {
        return '<p class="text-center"><a href="https://billmysales.com" target="_blank">BillMySales</a> es un proyecto de <a href="https://sasco.cl" target="_blank">SASCO SpA</a></p>';
    }

    /**
     * Método que crea el formulario que se debe mostrar en la página de configuración
     */
    protected function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBillMySalesModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];
        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Estructura del formulario de configuración
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                'title' => $this->l('Opciones de notificaciones'),
                'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Envío de notificaciones'),
                        'name' => 'BILLMYSALES_ACTIVE',
                        'is_bool' => true,
                        'desc' => $this->l('Recuerda que la integración debe estar activa en BillMySales para que el módulo funcione.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Activo'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Inactivo'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Registro de notificaciones'),
                        'name' => 'BILLMYSALES_LOG',
                        'is_bool' => true,
                        'desc' => $this->l('Se recomienda activar esta opción sólo si es necesario hacer una revisión de la integración en PrestaShop.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Activo'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Inactivo'),
                            ],
                        ],
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'label' => $this->l('Webhook de notificaciones'),
                        'name' => 'BILLMYSALES_WEBHOOK',
                        'prefix' => '<i class="icon-exchange"></i>',
                        'desc' => $this->l('URL del webhook de la pasarela de facturación en BillMySales.'),
                        'required' => true,
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'label' => $this->l('Token del webhook'),
                        'name' => 'BILLMYSALES_TOKEN',
                        'prefix' => '<i class="icon-key"></i>',
                        'desc' => $this->l('Clave secreta para validar el envío de las notificaciones a BillMySales.'),
                        'required' => true,
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Estados que notifican'),
                        'name' => 'BILLMYSALES_NOTIFY_STATUSES',
                        'desc' => $this->l('Solo se notificará a BillMySales cuando el pedido cambie a uno de estos estados. Si no marca ninguno, no se enviará ninguna notificación. Se recomienda marcar solo el estado que corresponde a "pago aceptado", para no reprocesar el mismo pedido más de una vez.'),
                        'values' => [
                            'query' => OrderState::getOrderStates((int)$this->context->language->id),
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar'),
                ],
            ],
        ];
    }

    /**
     * Métood que asigna valores a los campos del formulario
     */
    protected function getConfigFormValues()
    {
        $config = [];
        foreach ($this->defaultConfig as $key => $value) {
            if ($key === 'BILLMYSALES_NOTIFY_STATUSES') {
                continue; // se arma aparte más abajo: es un checkbox por cada estado
            }
            $config[$key] = Configuration::get($key, $value);
        }

        // el helper de checkboxes de PrestaShop espera una clave
        // "NOMBRE_DEL_CAMPO_<id>" por cada casilla (ver
        // views/helpers/form/form.tpl), no un único valor
        $selected = $this->getNotifyStatuses();
        foreach (OrderState::getOrderStates((int)$this->context->language->id) as $state) {
            $config['BILLMYSALES_NOTIFY_STATUSES_'.$state['id_order_state']] =
                in_array((string)$state['id_order_state'], $selected, true);
        }

        return $config;
    }

    /**
     * Método que devuelve los IDs (como string) de los estados de pedido
     * configurados para notificar a BillMySales (ver
     * hookActionOrderStatusPostUpdate())
     *
     * @return string[]
     */
    protected function getNotifyStatuses()
    {
        $statuses = json_decode((string)Configuration::get('BILLMYSALES_NOTIFY_STATUSES'), true);
        return is_array($statuses) ? $statuses : [];
    }

    /**
     * Método que guarda los datos pasados al formulario
     */
    protected function postProcess()
    {
        foreach (array_keys($this->defaultConfig) as $key) {
            if ($key === 'BILLMYSALES_NOTIFY_STATUSES') {
                continue; // se guarda aparte, ver más abajo
            }
            Configuration::updateValue($key, Tools::getValue($key));
        }

        // se arma la lista de estados marcados a partir de los checkboxes
        // individuales BILLMYSALES_NOTIFY_STATUSES_<id_order_state> que
        // PrestaShop genera para este campo (ver getConfigForm())
        $selected = [];
        foreach (OrderState::getOrderStates((int)$this->context->language->id) as $state) {
            if (Tools::getValue('BILLMYSALES_NOTIFY_STATUSES_'.$state['id_order_state'])) {
                $selected[] = (string)$state['id_order_state'];
            }
        }
        Configuration::updateValue('BILLMYSALES_NOTIFY_STATUSES', json_encode($selected));
    }

    /**
     * Método que devuelve la lista de campos personalizados configurados
     * para el formulario de dirección del checkout (ver
     * hookAdditionalCustomerAddressFields())
     *
     * @return array Cada elemento: ['key','label','values' => array,'required' => bool]
     */
    protected function getCustomFields()
    {
        $fields = json_decode((string)Configuration::get('BILLMYSALES_CUSTOM_FIELDS'), true);
        return is_array($fields) ? $fields : [];
    }

    /**
     * Método que arma el bloque HTML de administración de los campos
     * personalizados que se agregan al formulario de dirección del checkout
     */
    protected function renderCustomFieldsForm()
    {
        $fields = $this->getCustomFields();
        // si no hay ningún campo guardado todavía, se muestra una fila vacía
        // para que el formulario no aparezca en blanco sin nada que llenar
        if (empty($fields)) {
            $fields = [
                ['key' => '', 'label' => '', 'values' => [], 'required' => false],
            ];
        }
        // los valores se muestran en el formulario separados por coma
        foreach ($fields as &$field) {
            $field['values'] = implode(', ', $field['values']);
        }
        unset($field);

        $this->context->smarty->assign([
            'billmysales_fields' => $fields,
            'billmysales_current_index' => $this->context->link->getAdminLink('AdminModules', false)
                .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name,
            'billmysales_token' => Tools::getAdminTokenLite('AdminModules'),
        ]);

        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/custom_fields.tpl');
    }

    /**
     * Método que guarda la lista de campos personalizados del checkout
     * enviada desde el formulario de administración
     */
    protected function postProcessCustomFields()
    {
        $raw_fields = Tools::getValue('billmysales_fields');
        $clean = [];
        $used_keys = [];

        if (is_array($raw_fields)) {
            // se recolectan primero las claves de TODOS los campos que llegan
            // (editados y nuevos) antes de generar ninguna clave nueva, para
            // no terminar con dos campos con la misma clave interna
            foreach ($raw_fields as $raw_field) {
                if (!empty($raw_field['key'])) {
                    $used_keys[] = Tools::str2url($raw_field['key']);
                }
            }

            foreach ($raw_fields as $raw_field) {
                if (empty($raw_field['label'])) {
                    continue; // fila vacía (ej. una que quedó al agregar de más), se descarta
                }

                $label = trim(strip_tags($raw_field['label']));

                // si el campo ya existía (edición), se reutiliza su clave para
                // no perder el vínculo con datos ya guardados en direcciones
                // anteriores; si es un campo nuevo, se genera una a partir de
                // la etiqueta
                $key = !empty($raw_field['key'])
                    ? Tools::str2url($raw_field['key'])
                    : $this->generateCustomFieldKey($label, $used_keys);
                $used_keys[] = $key;

                // valores separados por coma -> selector; vacío -> texto libre
                $values = [];
                $values_raw = isset($raw_field['values']) ? trim($raw_field['values']) : '';
                if ($values_raw !== '') {
                    foreach (explode(',', $values_raw) as $value) {
                        $value = trim(strip_tags($value));
                        if ($value !== '') {
                            $values[] = $value;
                        }
                    }
                }

                $clean[] = [
                    'key' => $key,
                    'label' => $label,
                    'values' => $values,
                    'required' => !empty($raw_field['required']),
                ];
            }
        }

        Configuration::updateValue('BILLMYSALES_CUSTOM_FIELDS', json_encode($clean));
    }

    /**
     * Método que genera una clave (interna) única a partir de la etiqueta de
     * un campo nuevo, evitando choques con claves ya usadas en el mismo
     * guardado
     */
    private function generateCustomFieldKey($label, $used_keys)
    {
        $base = Tools::str2url($label);
        if ($base === '') {
            $base = 'campo';
        }

        $key = $base;
        $i = 2;
        while (in_array($key, $used_keys, true)) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }

    /**
     * Archivos CSS y JavaScript para el backoffice
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Archivos CSS y JavaScript para la tienda
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * Método que agrega los campos personalizados configurados al
     * formulario de dirección que PrestaShop usa tanto en "Mis direcciones"
     * como en el paso de dirección del checkout
     */
    public function hookAdditionalCustomerAddressFields(array $params = [])
    {
        $extra_fields = [];

        foreach ($this->getCustomFields() as $custom_field) {
            $field = new FormField();
            $field->setName('billmysales_'.$custom_field['key'])
                ->setLabel($custom_field['label'])
                ->setRequired(!empty($custom_field['required']));

            if (!empty($custom_field['values'])) {
                $field->setType('select');
                foreach ($custom_field['values'] as $value) {
                    $field->addAvailableValue($value, $value);
                }
            } else {
                $field->setType('text');
            }

            $extra_fields[] = $field;
        }

        return $extra_fields;
    }

    /**
     * Método que guarda los valores enviados para los campos personalizados
     * cuando se crea una dirección (PrestaShop no los guarda solo, ver
     * hookAdditionalCustomerAddressFields())
     */
    public function hookActionObjectAddressAddAfter(array $params = [])
    {
        if (!empty($params['object']) && $params['object'] instanceof Address) {
            $this->saveAddressCustomFields($params['object']);
        }
    }

    /**
     * Igual que hookActionObjectAddressAddAfter(), pero cuando la dirección
     * se actualiza en vez de crearse
     */
    public function hookActionObjectAddressUpdateAfter(array $params = [])
    {
        if (!empty($params['object']) && $params['object'] instanceof Address) {
            $this->saveAddressCustomFields($params['object']);
        }
    }

    /**
     * Método que borra los valores de campos personalizados guardados para
     * una dirección cuando esta se elimina, para no dejar datos huérfanos
     * en la tabla propia del módulo
     */
    public function hookActionObjectAddressDeleteAfter(array $params = [])
    {
        if (empty($params['object']) || !($params['object'] instanceof Address)) {
            return;
        }

        Db::getInstance()->execute(
            'DELETE FROM `'._DB_PREFIX_.'billmysales_address_field`
            WHERE `id_address` = '.(int)$params['object']->id
        );
    }

    /**
     * Método que guarda en la tabla propia del módulo los valores enviados
     * para los campos personalizados de una dirección
     */
    private function saveAddressCustomFields(Address $address)
    {
        $custom_fields = $this->getCustomFields();
        if (empty($custom_fields) || !$address->id) {
            return;
        }

        foreach ($custom_fields as $custom_field) {
            $value = Tools::getValue('billmysales_'.$custom_field['key']);
            if ($value === false) {
                continue; // el campo no vino en este envío (ej. guardado desde el back office)
            }

            Db::getInstance()->execute(
                'REPLACE INTO `'._DB_PREFIX_.'billmysales_address_field`
                (`id_address`, `field_key`, `field_value`)
                VALUES ('.(int)$address->id.', \''.pSQL($custom_field['key']).'\', \''.pSQL($value).'\')'
            );
        }
    }

    /**
     * Método que devuelve los valores de campos personalizados guardados
     * para una dirección, para incluirlos en el pedido que se envía a
     * BillMySales (ver hookActionOrderStatusPostUpdate())
     *
     * @return array ['clave' => 'valor', ...]
     */
    private function getAddressCustomFieldValues($id_address)
    {
        if (empty($id_address)) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `field_key`, `field_value`
            FROM `'._DB_PREFIX_.'billmysales_address_field`
            WHERE `id_address` = '.(int)$id_address
        );

        $values = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $values[$row['field_key']] = $row['field_value'];
            }
        }
        return $values;
    }

    /**
     * Método que agrega, en la columna derecha del detalle del pedido
     * (debajo de "Payment"), un bloque para completar/editar los campos
     * personalizados de facturación (RUT, Giro, etc.). Es necesario
     * porque el formulario de dirección del ADMIN (a diferencia del
     * checkout) no tiene el hook additionalCustomerAddressFields — así
     * que un pedido creado a mano desde el admin nunca pasa por ahí y
     * queda sin esos datos.
     */
    public function hookDisplayAdminOrderMainBottom(array $params = [])
    {
        $custom_fields = $this->getCustomFields();
        if (empty($custom_fields) || empty($params['id_order'])) {
            return '';
        }

        $order = new Order((int)$params['id_order']);
        if (!Validate::isLoadedObject($order) || !$order->id_address_invoice) {
            return '';
        }

        $address = new Address((int)$order->id_address_invoice);
        if (!Validate::isLoadedObject($address)) {
            return '';
        }

        // guardar si el formulario de este bloque se envió (self-submit:
        // la página del pedido se vuelve a cargar con los mismos datos)
        if (Tools::isSubmit('submitBillMySalesOrderFields')
            && (int)Tools::getValue('billmysales_id_order') === (int)$order->id
        ) {
            $this->saveAddressCustomFields($address);
        }

        $this->context->smarty->assign([
            'billmysales_order_id' => $order->id,
            'billmysales_fields' => $custom_fields,
            'billmysales_values' => $this->getAddressCustomFieldValues($address->id),
        ]);

        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/order_fields.tpl');
    }

    /**
     * Método que agrega nuestros campos personalizados de facturación
     * DENTRO del formulario nativo de "Crear pedido" del admin (ver
     * override en views/PrestaShop/Admin/Sell/Order/Order/Blocks/Create/
     * summary.html.twig). Como el pedido todavía no existe, no hay
     * valores previos que precargar; los valores enviados se capturan y
     * guardan en hookActionOrderStatusPostUpdate() (mismo POST, se
     * procesa junto con el resto al crear el pedido).
     */
    public function hookDisplayAdminOrderCreateFields(array $params = [])
    {
        $custom_fields = $this->getCustomFields();
        if (empty($custom_fields)) {
            return '';
        }

        $this->context->smarty->assign('billmysales_fields', $custom_fields);

        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/order_create_fields.tpl');
    }

    /**
     * Método que se ejecuta mediante un hook cuando el estado de la orden es actualizado
     * Este método es el necesario para detectar el método pagado y poder crear el DTE
     */
    public function hookActionOrderStatusPostUpdate(array $params = [])
    {
        // no hay nuevo estado de la orden
        if (empty($params['newOrderStatus']) || empty($params['id_order'])) {
            return;
        }
        // solo notificar en los estados marcados en "Estados que
        // notifican" (pestaña Configuración) — si no, se dispara en
        // CUALQUIER cambio de estado y BillMySales termina recibiendo
        // varias notificaciones para el mismo pedido (una por cada
        // cambio), rechazando las repetidas
        if (!in_array((string)$params['newOrderStatus']->id, $this->getNotifyStatuses(), true)) {
            return;
        }
        // crear objetos que se usarán para extraer datos
        $Order = new Order((int)$params['id_order']);
        $Customer = new Customer((int)$Order->id_customer);
        $Cart = new Cart($Order->id_cart);
        $Address = new Address($Cart->id_address_delivery);
        $Billing = new Address($Cart->id_address_invoice);
        $Carrier = new Carrier((int)($Order->id_carrier));
        $Shop = new Shop((int)($Order->id_shop));
        // construir arreglo con los datos que se usarán
        $order = array_merge($params, get_object_vars($Order));
        $order['customer'] = get_object_vars($Customer);
        unset($order['customer']['passwd']);
        $order['cart'] = get_object_vars($Cart);
        $order['cart']['rules'] = $Cart->getCartRules();
        $order['address'] = get_object_vars($Address);
        $order['billing'] = get_object_vars($Billing);
        $order['billing']['custom_fields'] = $this->getAddressCustomFieldValues($Billing->id);
        $order['carrier'] = get_object_vars($Carrier);
        $order['products'] = $Order->getProducts();
        $order['detail'] = $Order->getOrderDetailList();
        $order['shop'] = get_object_vars($Shop);
        unset($order['shop']['theme']);
        // llamar al método que procesa la orden pagada
        return $this->processOrderPaid($order);
    }

    /**
     * Método que procesa la orden con todos sus datos enviando la solicitud a BillMySales
     * El resultado de la llamada a BillMySales es guardado como nota del documento asociado al pedido
     */
    private function processOrderPaid($order)
    {
        // log para la orden que se enviará
        if (Configuration::get('BILLMYSALES_LOG')) {
            PrestaShopLogger::addLog('BillMySales Data Order #'.$order['id_order'].': '.base64_encode(json_encode($order)));
        }
        // notificar al servicio web de BillMySales
        $url = Configuration::get('BILLMYSALES_WEBHOOK');
        $response = $this->api_post($url, $order);
        // log para la respuesta del servicio web
        if (Configuration::get('BILLMYSALES_LOG')) {
            $msg = is_string($response) ? $response : base64_encode(json_encode($response));
            PrestaShopLogger::addLog('BillMySales Response Order #'.$order['id_order'].': '.$msg);
        }
        // todo ok
        return true;
    }

    /**
     * Método que hace un llamado por POST a BillMySales
     */
    private function api_post($url, $data)
    {
        $data = json_encode($data);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'X-PRESTASHOPBMS-HMAC-SHA256: ' . $this->sign_data($data),
            ],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    /**
     * Método que calcula la firma de los datos
     */
    private function sign_data($data)
    {
        $token = Configuration::get('BILLMYSALES_TOKEN');
        return base64_encode(hash_hmac('sha256', $data, $token, true));
    }

}

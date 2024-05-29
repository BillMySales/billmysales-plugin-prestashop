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
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

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
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('actionOrderStatusPostUpdate');

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
        // procesar valores pasados en el formulario
        if (((bool)Tools::isSubmit('submitBillMySalesModule')) == true) {
            $this->postProcess();
        }
        // armar página del formulario
        $this->context->smarty->assign('module_dir', $this->_path);
        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        return $output.$this->renderForm().$this->footer();
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
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Estructura del formulario de configuración
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Opciones de notificaciones'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Envío de notificaciones'),
                        'name' => 'BILLMYSALES_ACTIVE',
                        'is_bool' => true,
                        'desc' => $this->l('Recuerda que la integración debe estar activa en BillMySales para que el módulo funcione.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Activo')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Inactivo')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Registro de notificaciones'),
                        'name' => 'BILLMYSALES_LOG',
                        'is_bool' => true,
                        'desc' => $this->l('Se recomienda activar esta opción sólo si es necesario hacer una revisión de la integración en PrestaShop.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Activo')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Inactivo')
                            )
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'label' => $this->l('Webhook de notificaciones'),
                        'name' => 'BILLMYSALES_WEBHOOK',
                        'prefix' => '<i class="icon-exchange"></i>',
                        'desc' => $this->l('URL del webhook de la pasarela de facturación en BillMySales.'),
                        'required' => true,
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'label' => $this->l('Token del webhook'),
                        'name' => 'BILLMYSALES_TOKEN',
                        'prefix' => '<i class="icon-key"></i>',
                        'desc' => $this->l('Clave secreta para validar el envío de las notificaciones a BillMySales.'),
                        'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Guardar'),
                ),
            ),
        );
    }

    /**
     * Métood que asigna valores a los campos del formulario
     */
    protected function getConfigFormValues()
    {
        $config = [];
        foreach ($this->defaultConfig as $key => $value) {
            $config[$key] = Configuration::get($key, $value);
        }
        return $config;
    }

    /**
     * Método que guarda los datos pasados al formulario
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Archivos CSS y JavaScript para el backoffice
     */
    public function hookBackOfficeHeader()
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
     * Método que se ejecuta mediante un hook cuando el estado de la orden es actualizado
     * Este método es el necesario para detectar el método pagado y poder crear el DTE
     */
    public function hookActionOrderStatusPostUpdate(array $params = [])
    {
        // no hay nuevo estado de la orden
        if (empty($params['newOrderStatus']) || empty($params['id_order'])) {
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
                'X-PRESTASHOPBMS-HMAC-SHA256: ' . $this->sign_data($data)
            ],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        echo(json_decode($response, true));
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

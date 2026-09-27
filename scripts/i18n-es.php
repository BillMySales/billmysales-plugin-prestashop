<?php

declare(strict_types=1);

/**
 * Spanish translations, by [source][English string] (source: the module
 * name for a PHP string, the template's filename for one from a .tpl).
 * After adding or changing a source string, run `make i18n`: it re-scans
 * the plugin (scripts/i18n.php) and applies this file's translations onto
 * the resulting keys (scripts/i18n-apply.php), reporting any new source
 * string that still needs a translation added here.
 */

return [
    'billmysales' => [
        'BillMySales' => 'BillMySales',
        'Sends orders to BillMySales when they reach the selected order states, and adds custom billing fields (e.g. tax id, business activity) to the checkout.' => 'Envía los pedidos a BillMySales cuando alcanzan los estados de pedido seleccionados, y agrega campos de facturación personalizados (p. ej. RUT, giro) al checkout.',
        'This will stop automatic billing through BillMySales.' => 'Esto detendrá la facturación automática a través de BillMySales.',
        'Deliveries to BillMySales are turned off.' => 'El envío a BillMySales está desactivado.',
        'The BillMySales notification URL is not set.' => 'No se ha configurado la URL de notificación de BillMySales.',
        'Notification settings' => 'Configuración de notificaciones',
        'Deliveries' => 'Envíos',
        'The integration must also be active in BillMySales for orders to be sent.' => 'La integración también debe estar activa en BillMySales para que los pedidos se envíen.',
        'Active' => 'Activo',
        'Inactive' => 'Inactivo',
        'Notification URL' => 'URL de notificación',
        'The webhook URL given by BillMySales.' => 'La URL del webhook entregada por BillMySales.',
        'Secret' => 'Clave secreta',
        'The secret shared with BillMySales, used to sign each notification.' => 'La clave secreta compartida con BillMySales, usada para firmar cada notificación.',
        'Payload format' => 'Formato de los datos',
        '"Standard" is what BillMySales parses today. "Webservice" sends PrestaShop\'s own webservice representation of the order instead; use it only if your BillMySales integration already expects it.' => '"Estándar" es lo que BillMySales procesa hoy. "Webservice" envía en cambio la representación del pedido propia del webservice de PrestaShop; úselo solo si su integración con BillMySales ya lo espera.',
        'Standard' => 'Estándar',
        'Webservice' => 'Webservice',
        'Order states that notify' => 'Estados de pedido que notifican',
        'BillMySales is notified only when an order reaches one of these states. Select only the state that means the payment was accepted, so an order is not sent more than once.' => 'BillMySales solo es notificado cuando un pedido alcanza uno de estos estados. Seleccione solo el estado que significa que el pago fue aceptado, para que un pedido no se envíe más de una vez.',
        'Save' => 'Guardar',
        'Sent' => 'Enviado',
        'Retrying' => 'Reintentando',
        'Not sent, no more retries' => 'No enviado, sin más reintentos',
        'Rejected' => 'Rechazado',
    ],
    'configure' => [
        'BillMySales sends your orders to your billing platform when they reach the order state you choose, so you never have to bill them by hand.' => 'BillMySales envía sus pedidos a su plataforma de facturación cuando alcanzan el estado que usted elija, para que nunca tenga que facturarlos a mano.',
        'Learn more at' => 'Más información en',
        'Settings' => 'Configuración',
        'Checkout fields' => 'Campos del checkout',
    ],
    'custom_fields' => [
        'Checkout fields' => 'Campos del checkout',
        'These fields are added to the address form at checkout (and "My addresses"), and sent to BillMySales with the order.' => 'Estos campos se agregan al formulario de dirección del checkout (y a "Mis direcciones"), y se envían a BillMySales junto con el pedido.',
        'Add another field' => 'Agregar otro campo',
        'Save fields' => 'Guardar campos',
    ],
    'custom_fields_row' => [
        'Label' => 'Etiqueta',
        'E.g. Tax id, Company name, Business activity' => 'Ej: RUT, Razón social, Giro comercial',
        'Key:' => 'Clave:',
        'Values (optional)' => 'Valores (opcional)',
        'E.g. Receipt, Invoice' => 'Ej: Boleta, Factura',
        'Comma-separated values show the field as a list of those options; leave it empty for a free text field.' => 'Valores separados por coma muestran el campo como una lista de esas opciones; déjelo vacío para un campo de texto libre.',
        'Required' => 'Obligatorio',
        'The customer must fill it in to place the order.' => 'El cliente debe completarlo para poder realizar el pedido.',
        'Remove this field' => 'Eliminar este campo',
    ],
    'order_block' => [
        'BillMySales' => 'BillMySales',
        'Delivery status:' => 'Estado del envío:',
        'Not sent yet.' => 'Aún no enviado.',
        'If this order was not placed through the store checkout (e.g. it was created from the back office), fill in the billing data BillMySales needs here.' => 'Si este pedido no se realizó a través del checkout de la tienda (p. ej. se creó desde el back office), complete aquí los datos de facturación que BillMySales necesita.',
        '-- Select --' => '-- Seleccionar --',
        'Save' => 'Guardar',
        'Send to BillMySales' => 'Enviar a BillMySales',
    ],
    'order_create_fields' => [
        'BillMySales' => 'BillMySales',
        '-- Select --' => '-- Seleccionar --',
    ],
];

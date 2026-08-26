Plugin BillMySales para PrestaShop
==================================

Este módulo realiza el envío de los datos de un pedido a [BillMySales](https://billmysales.com)
para realizar el proceso de facturación del pedido.

El envío se dispara cuando el pedido cambia a alguno de los estados marcados en
**Configuración → Estados que notifican** (por ejemplo "Pago aceptado"). El pedido se
procesará según las reglas definidas en la pasarela de facturación en BillMySales.

### Campos personalizados de facturación (RUT, Giro, Documento, etc.)

Desde **Configuración → Campos personalizados** se pueden definir campos extra (de texto
libre o con opciones a elegir) que se agregan al formulario de dirección del checkout y a
"Mis direcciones" — por ejemplo RUT, Giro comercial o Tipo de documento. Los valores que
completa el cliente viajan junto con el resto de los datos del pedido a BillMySales.

Si un pedido se crea desde el administrador (en vez de por el checkout de la tienda), esos
mismos campos se pueden completar directamente ahí: en la pantalla "Crear pedido", y en el
detalle de cualquier pedido ya creado (sección "Datos de facturación (BillMySales)"). Si un
campo está marcado como obligatorio y queda vacío, el módulo no envía la notificación a
BillMySales hasta que se complete — evita mandar un pedido con datos de facturación
incompletos.

El plugin fue probado con PrestaShop 8.1.7 (PHP 8.1), instalado tanto en modo desarrollo
como desde el .zip generado a partir de este código fuente. Es compatible desde PrestaShop 1.7
(ver `ps_versions_compliancy` en `billmysales.php`).

![Configuración del módulo en PrestaShop](https://i.imgur.com/KIZEhFf.png "Configuración del módulo en PrestaShop")

Instalar a partir de este código fuente
---------------------------------------

1. Descargar el [código de este repositorio](https://github.com/BillMySales/billmysales-plugin-prestashop/archive/refs/heads/main.zip).
2. Descomprimir el archivo y renombrar la carpeta a `billmysales`.
3. Comprimir la carpeta renombrada.
4. Subir el módulo a PrestaShop.

Licencia
--------

Este código está liberado bajo la licencia de software libre [AGPL](http://www.gnu.org/licenses/agpl-3.0.en.html).
Para detalles sobre cómo se puede utilizar, modificar y/o distribuir este plugin revisar los términos de la licencia.

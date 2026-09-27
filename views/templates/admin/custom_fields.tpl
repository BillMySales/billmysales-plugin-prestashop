{*
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
 *
 * Pestaña "Campos personalizados": lista dinámica de campos que el admin
 * puede agregar/editar/eliminar. Se agregan al formulario de dirección del
 * checkout mediante el hook additionalCustomerAddressFields (ver
 * hookAdditionalCustomerAddressFields() en billmysales.php).
 *}

<div class="panel">
    <h3><i class="icon icon-list-alt"></i> {l s='Campos personalizados del checkout' mod='billmysales'}</h3>
    <p>
        {l s='Estos campos se agregan al formulario de dirección del checkout (y a "Mis direcciones"), y viajan junto con el pedido a BillMySales.' mod='billmysales'}
    </p>

    <form method="post" action="{$billmysales_current_index|escape:'html':'UTF-8'}&token={$billmysales_token|escape:'html':'UTF-8'}&billmysales_tab=campos">
        <div id="billmysales-fields">
            {foreach from=$billmysales_fields item=field key=index}
                {include file="./custom_fields_row.tpl" field=$field index=$index}
            {/foreach}
        </div>

        <p>
            <button type="button" class="btn btn-default" id="billmysales-add-field">
                <i class="icon icon-plus"></i> {l s='Agregar otro campo' mod='billmysales'}
            </button>
        </p>

        <div class="panel-footer">
            <button type="submit" name="submitBillMySalesCustomFields" class="btn btn-primary pull-right">
                {l s='Guardar campos' mod='billmysales'}
            </button>
        </div>
    </form>
</div>

{* Plantilla oculta que back.js clona para agregar filas nuevas. *}
<template id="billmysales-field-template">
    {include file="./custom_fields_row.tpl" field=['key' => '', 'label' => '', 'values' => '', 'required' => false] index='__INDEX__'}
</template>

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
 * Bloque en el detalle del pedido (admin) para completar/editar los
 * campos personalizados de facturación (ver
 * hookDisplayAdminOrderMainBottom() en billmysales.php). Usa las mismas
 * clases .card/.card-header/.card-body que el resto de los bloques
 * nativos del detalle del pedido (Customer, Messages, etc.) para que se
 * vea igual.
 *}

<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            {l s='Datos de facturación (BillMySales)' mod='billmysales'}
        </h3>
    </div>

    <div class="card-body">
        <p class="text-muted">
            {l s='Si este pedido no pasó por el checkout de la tienda (ej. se creó desde el admin), complete acá los datos que se envían a BillMySales.' mod='billmysales'}
        </p>

        <form method="post">
            <input type="hidden" name="billmysales_id_order" value="{$billmysales_order_id}">

            <div class="row">
                {foreach from=$billmysales_fields item=field}
                    <div class="form-group col-md-4">
                        <label>
                            {$field.label|escape:'html':'UTF-8'}{if !empty($field.required)} *{/if}
                        </label>
                        {if !empty($field.values)}
                            <select
                                name="billmysales_{$field.key}"
                                class="form-control"
                                {if !empty($field.required)}required{/if}
                            >
                                <option value="">{l s='-- Seleccionar --' mod='billmysales'}</option>
                                {foreach from=$field.values item=option}
                                    <option
                                        value="{$option|escape:'html':'UTF-8'}"
                                        {if isset($billmysales_values[$field.key]) && $billmysales_values[$field.key] === $option}selected="selected"{/if}
                                    >
                                        {$option|escape:'html':'UTF-8'}
                                    </option>
                                {/foreach}
                            </select>
                        {else}
                            <input
                                type="text"
                                class="form-control"
                                name="billmysales_{$field.key}"
                                value="{if isset($billmysales_values[$field.key])}{$billmysales_values[$field.key]|escape:'html':'UTF-8'}{/if}"
                                {if !empty($field.required)}required{/if}
                            >
                        {/if}
                    </div>
                {/foreach}
            </div>

            <button type="submit" name="submitBillMySalesOrderFields" class="btn btn-primary">
                {l s='Guardar' mod='billmysales'}
            </button>
        </form>
    </div>
</div>

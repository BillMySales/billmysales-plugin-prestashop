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
 * A diferencia de order_fields.tpl (detalle del pedido), esta NO tiene
 * su propio <form>/botón: se inyecta DENTRO del formulario de "Crear
 * pedido" (ver hookDisplayAdminOrderCreateFields() en billmysales.php),
 * así que los valores viajan junto con el resto al hacer clic en
 * "Create order".
 *}

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-header-title">
            {l s='Datos de facturación (BillMySales)' mod='billmysales'}
        </h3>
    </div>

    <div class="card-body">
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
                                <option value="{$option|escape:'html':'UTF-8'}">
                                    {$option|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    {else}
                        <input
                            type="text"
                            class="form-control"
                            name="billmysales_{$field.key}"
                            {if !empty($field.required)}required{/if}
                        >
                    {/if}
                </div>
            {/foreach}
        </div>
    </div>
</div>

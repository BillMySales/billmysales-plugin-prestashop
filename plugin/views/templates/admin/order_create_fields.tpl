{*
 * BillMySales for PrestaShop.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the European Union Public Licence v. 1.2 (EUPL-1.2).
 * See LICENSE file for more details.
 *
 * Injected inside the "Add new order" form (see
 * hookDisplayAdminOrderCreateFields() and the summary.html.twig override):
 * no <form>/button of its own, its values travel with the rest when the
 * order is created.
 *}

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-header-title">{l s='BillMySales' mod='billmysales'}</h3>
    </div>
    <div class="card-body">
        <div class="row">
            {foreach from=$billmysales_fields item=field}
                <div class="form-group col-md-4">
                    <label>{$field.label|escape:'html':'UTF-8'}{if $field.required} *{/if}</label>
                    {if $field.values}
                        <select name="billmysales_{$field.key}" class="form-control" {if $field.required}required{/if}>
                            <option value="">{l s='-- Select --' mod='billmysales'}</option>
                            {foreach from=$field.values item=option}
                                <option value="{$option|escape:'html':'UTF-8'}">{$option|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    {else}
                        <input type="text" class="form-control" name="billmysales_{$field.key}" {if $field.required}required{/if}>
                    {/if}
                </div>
            {/foreach}
        </div>
    </div>
</div>

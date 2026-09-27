{*
 * BillMySales for PrestaShop.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the European Union Public Licence v. 1.2 (EUPL-1.2).
 * See LICENSE file for more details.
 *
 * "Billing data" block on the order detail page: the custom fields
 * (editable) and the last known BillMySales delivery status.
 *}

<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">{l s='BillMySales' mod='billmysales'}</h3>
    </div>

    <div class="card-body">
        {if $billmysales.delivery_status}
            <p>
                <strong>{l s='Delivery status:' mod='billmysales'}</strong>
                {$billmysales.delivery_status.label|escape:'html':'UTF-8'}
                {if $billmysales.delivery_status.detail}
                    ({$billmysales.delivery_status.detail|escape:'html':'UTF-8'})
                {/if}
                &mdash; {$billmysales.delivery_status.updated_at|escape:'html':'UTF-8'}
            </p>
        {else}
            <p class="text-muted">{l s='Not sent yet.' mod='billmysales'}</p>
        {/if}

        {if $billmysales.fields}
            <p class="text-muted">
                {l s='If this order was not placed through the store checkout (e.g. it was created from the back office), fill in the billing data BillMySales needs here.' mod='billmysales'}
            </p>
            <form method="post">
                <input type="hidden" name="billmysales_id_order" value="{$billmysales_order_id}">
                <div class="row">
                    {foreach from=$billmysales.fields item=field}
                        <div class="form-group col-md-4">
                            <label>{$field.label|escape:'html':'UTF-8'}{if $field.required} *{/if}</label>
                            {if $field.values}
                                <select name="billmysales_{$field.key}" class="form-control" {if $field.required}required{/if}>
                                    <option value="">{l s='-- Select --' mod='billmysales'}</option>
                                    {foreach from=$field.values item=option}
                                        <option value="{$option|escape:'html':'UTF-8'}" {if $field.value === $option}selected="selected"{/if}>
                                            {$option|escape:'html':'UTF-8'}
                                        </option>
                                    {/foreach}
                                </select>
                            {else}
                                <input type="text" class="form-control" name="billmysales_{$field.key}" value="{$field.value|escape:'html':'UTF-8'}" {if $field.required}required{/if}>
                            {/if}
                        </div>
                    {/foreach}
                </div>
                <button type="submit" name="submitBillMySalesOrderFields" class="btn btn-primary">{l s='Save' mod='billmysales'}</button>
            </form>
        {/if}

        {if $billmysales.can_resend}
            <form method="post" class="mt-2">
                <input type="hidden" name="billmysales_id_order" value="{$billmysales_order_id}">
                <button type="submit" name="submitBillMySalesResend" class="btn btn-default">
                    {l s='Send to BillMySales' mod='billmysales'}
                </button>
            </form>
        {/if}
    </div>
</div>

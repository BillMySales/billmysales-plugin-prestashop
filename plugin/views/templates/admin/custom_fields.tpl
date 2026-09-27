{*
 * BillMySales for PrestaShop.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the European Union Public Licence v. 1.2 (EUPL-1.2).
 * See LICENSE file for more details.
 *}

<div class="panel">
    <h3><i class="icon icon-list-alt"></i> {l s='Checkout fields' mod='billmysales'}</h3>
    <p>
        {l s='These fields are added to the address form at checkout (and "My addresses"), and sent to BillMySales with the order.' mod='billmysales'}
    </p>

    <form method="post" action="{$billmysales_current_index|escape:'html':'UTF-8'}&billmysales_tab=fields">
        <div id="billmysales-fields">
            {foreach from=$billmysales_fields item=field}
                {include file="./custom_fields_row.tpl" field=$field}
            {/foreach}
        </div>

        <p>
            <button type="button" class="btn btn-default" id="billmysales-add-field">
                <i class="icon icon-plus"></i> {l s='Add another field' mod='billmysales'}
            </button>
        </p>

        <div class="panel-footer">
            <button type="submit" name="submitBillMySalesCustomFields" class="btn btn-primary pull-right">
                {l s='Save fields' mod='billmysales'}
            </button>
        </div>
    </form>
</div>

{* Hidden template row.js clones to add a field. *}
<template id="billmysales-field-template">
    {include file="./custom_fields_row.tpl" field=['index' => '__INDEX__', 'key' => '', 'label' => '', 'values' => '', 'required' => false]}
</template>

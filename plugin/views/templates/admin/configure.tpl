{*
 * BillMySales for PrestaShop.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the European Union Public Licence v. 1.2 (EUPL-1.2).
 * See LICENSE file for more details.
 *}

<div class="panel">
    <p>
        {l s='BillMySales sends your orders to your billing platform when they reach the order state you choose, so you never have to bill them by hand.' mod='billmysales'}
    </p>
    <p>
        {l s='Learn more at' mod='billmysales'} <a href="https://www.billmysales.com" target="_blank">www.billmysales.com</a>.
    </p>
</div>

<ul class="nav nav-tabs" style="margin-bottom:20px;">
    <li class="{if $billmysales_tab === 'settings'}active{/if}">
        <a href="{$billmysales_tab_url|escape:'html':'UTF-8'}&billmysales_tab=settings">
            <i class="icon icon-cogs"></i> {l s='Settings' mod='billmysales'}
        </a>
    </li>
    <li class="{if $billmysales_tab === 'fields'}active{/if}">
        <a href="{$billmysales_tab_url|escape:'html':'UTF-8'}&billmysales_tab=fields">
            <i class="icon icon-list-alt"></i> {l s='Checkout fields' mod='billmysales'}
        </a>
    </li>
</ul>

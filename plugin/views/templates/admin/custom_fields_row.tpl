{*
 * BillMySales for PrestaShop.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the European Union Public Licence v. 1.2 (EUPL-1.2).
 * See LICENSE file for more details.
 *
 * One custom field's row (label, values, required). Reused for the saved
 * fields and for the hidden template admin.js clones to add a row.
 * Expects $field: index, key, label, values (comma-separated), required.
 *}

<div class="panel billmysales-field-row" style="margin-bottom:15px;">
    <input type="hidden" name="billmysales_fields[{$field.index}][key]" value="{$field.key|escape:'html':'UTF-8'}">

    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Label' mod='billmysales'} *</label>
        <div class="col-lg-9">
            <input
                type="text"
                class="form-control"
                name="billmysales_fields[{$field.index}][label]"
                value="{$field.label|escape:'html':'UTF-8'}"
                placeholder="{l s='E.g. Tax id, Company name, Business activity' mod='billmysales'}"
            >
            {if $field.key}
                <p class="help-block">
                    {l s='Key:' mod='billmysales'} <code>{$field.key|escape:'html':'UTF-8'}</code>
                </p>
            {/if}
        </div>
    </div>

    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Values (optional)' mod='billmysales'}</label>
        <div class="col-lg-9">
            <input
                type="text"
                class="form-control"
                name="billmysales_fields[{$field.index}][values]"
                value="{$field.values|escape:'html':'UTF-8'}"
                placeholder="{l s='E.g. Receipt, Invoice' mod='billmysales'}"
            >
            <p class="help-block">
                {l s='Comma-separated values show the field as a list of those options; leave it empty for a free text field.' mod='billmysales'}
            </p>
        </div>
    </div>

    <div class="form-group">
        <label class="control-label col-lg-3">{l s='Required' mod='billmysales'}</label>
        <div class="col-lg-9">
            <label class="checkbox-inline">
                <input type="checkbox" name="billmysales_fields[{$field.index}][required]" value="1" {if !empty($field.required)}checked="checked"{/if}>
                {l s='The customer must fill it in to place the order.' mod='billmysales'}
            </label>
        </div>
    </div>

    <button type="button" class="btn btn-default billmysales-remove-field">
        <i class="icon icon-trash"></i> {l s='Remove this field' mod='billmysales'}
    </button>
</div>

/**
 * BillMySales for PrestaShop.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the European Union Public Licence v. 1.2 (EUPL-1.2).
 * See LICENSE file for more details.
 */

/**
 * Settings page: show/hide the secret, and add/remove checkout fields.
 */
(function () {
    'use strict';

    /**
     * Adds a button next to the secret field that shows or hides it (the
     * settings form has no HTML for it: the field is a plain password
     * input).
     *
     * @return {void}
     */
    function initSecretToggle() {
        var input = document.getElementById('BILLMYSALES_TOKEN');
        if (!input || !input.parentNode) {
            return;
        }
        var button = document.createElement('button');
        button.type = 'button';
        button.id = 'billmysales-secret-toggle';
        button.className = 'btn btn-default';
        button.innerHTML = '<i class="icon-eye"></i>';
        button.addEventListener('click', function () {
            input.type = input.type === 'password' ? 'text' : 'password';
        });
        var wrapper = document.createElement('div');
        wrapper.className = 'billmysales-secret-wrapper';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        wrapper.appendChild(button);
    }

    /**
     * The checkout fields tab: "Add another field" clones the row
     * template, and "Remove this field" removes its row.
     *
     * @return {void}
     */
    function initFields() {
        var container = document.getElementById('billmysales-fields');
        var addButton = document.getElementById('billmysales-add-field');
        var template = document.getElementById('billmysales-field-template');
        if (!container || !addButton || !template) {
            return;
        }
        var nextIndex = container.querySelectorAll('.billmysales-field-row').length;
        addButton.addEventListener('click', function () {
            var wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
            container.appendChild(wrapper.firstElementChild);
            nextIndex += 1;
        });
        container.addEventListener('click', function (event) {
            var removeButton = event.target.closest('.billmysales-remove-field');
            if (removeButton) {
                removeButton.closest('.billmysales-field-row').remove();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSecretToggle();
        initFields();
    });
})();

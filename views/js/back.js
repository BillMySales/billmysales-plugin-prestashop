/**
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
 * Pestaña "Campos personalizados": agrega/quita filas de campos en el
 * formulario, clonando la plantilla oculta #billmysales-field-template
 * (ver views/templates/admin/custom_fields.tpl).
 */

document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('billmysales-fields');
    var addButton = document.getElementById('billmysales-add-field');
    var template = document.getElementById('billmysales-field-template');

    if (!container || !addButton || !template) {
        return;
    }

    var nextIndex = container.querySelectorAll('.billmysales-field-row').length;

    addButton.addEventListener('click', function () {
        var html = template.innerHTML.replace(/__INDEX__/g, nextIndex);
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        container.appendChild(wrapper.firstElementChild);
        nextIndex++;
    });

    container.addEventListener('click', function (event) {
        var removeButton = event.target.closest('.billmysales-remove-field');
        if (!removeButton) {
            return;
        }
        removeButton.closest('.billmysales-field-row').remove();
    });
});

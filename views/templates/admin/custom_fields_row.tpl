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
 * Fila de UN campo personalizado (etiqueta + valores + obligatorio).
 * Se reutiliza tanto para campos ya guardados como para la plantilla
 * vacía que usa back.js al agregar filas nuevas.
 * Variables esperadas: $field (['key','label','values','required']), $index.
 *}

<div class="panel billmysales-field-row" style="margin-bottom:15px;">
    <input
        type="hidden"
        name="billmysales_fields[{$index}][key]"
        value="{$field.key|escape:'html':'UTF-8'}"
    >

    <div class="form-group">
        <label class="control-label col-lg-3">
            {l s='Etiqueta' mod='billmysales'} *
        </label>
        <div class="col-lg-9">
            <input
                type="text"
                class="form-control"
                name="billmysales_fields[{$index}][label]"
                value="{$field.label|escape:'html':'UTF-8'}"
                placeholder="{l s='Ej: RUT, Razón social, Giro comercial' mod='billmysales'}"
            >
            {if $field.key}
                <p class="help-block">
                    {l s='Clave interna:' mod='billmysales'} <code>{$field.key|escape:'html':'UTF-8'}</code>
                    {l s='(no cambia aunque edites la etiqueta)' mod='billmysales'}
                </p>
            {/if}
        </div>
    </div>

    <div class="form-group">
        <label class="control-label col-lg-3">
            {l s='Valores (opcional)' mod='billmysales'}
        </label>
        <div class="col-lg-9">
            <input
                type="text"
                class="form-control"
                name="billmysales_fields[{$index}][values]"
                value="{$field.values|escape:'html':'UTF-8'}"
                placeholder="{l s='Ej: Persona natural, Persona jurídica' mod='billmysales'}"
            >
            <p class="help-block">
                {l s='Si escribes valores separados por coma, el campo se mostrará como un selector con esas opciones. Si lo dejas vacío, será un campo de texto libre.' mod='billmysales'}
            </p>
        </div>
    </div>

    <div class="form-group">
        <label class="control-label col-lg-3">
            {l s='Obligatorio' mod='billmysales'}
        </label>
        <div class="col-lg-9">
            <label class="checkbox-inline">
                <input
                    type="checkbox"
                    name="billmysales_fields[{$index}][required]"
                    value="1"
                    {if !empty($field.required)}checked="checked"{/if}
                >
                {l s='El cliente debe completarlo para poder pagar.' mod='billmysales'}
            </label>
        </div>
    </div>

    <button type="button" class="btn btn-default billmysales-remove-field">
        <i class="icon icon-trash"></i> {l s='Eliminar este campo' mod='billmysales'}
    </button>
</div>

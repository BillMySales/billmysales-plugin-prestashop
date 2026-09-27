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
 *}

<div class="panel">
    <h3><i class="icon icon-question-sign"></i> {l s='¿Pierdes mucho tiempo facturando a mano?' mod='billmysales'}</h3>
    <h4>{l s='Automatiza tus facturas y preocúpate de vender.' mod='billmysales'}</h4>
    <p>{l s='BillMySales se encarga de procesar los pedidos de la tienda y pasarlos a tu facturador favorito. Así te puedes enfocar en hacer crecer tu negocio.' mod='billmysales'}</p>
    <p>{l s='Si usas ' mod='billmysales'}<a href="https://libredte.cl" target="_blank">LibreDTE</a>{l s=' o ' mod='billmysales'}<a href="https://bhexpress.cl" target="_blank">BHExpress</a>{l s=', contáctanos para obtener una cuenta gratuita en BillMySales.' mod='billmysales'}</p>
   <div class="row">
	<div class="alert alert-info">
	  <p>{l s='Para conocer cómo funciona BillMySales, sus características y planes, revisa ' mod='billmysales'}<a href="https://billmysales.com" target="_blank">www.billmysales.com</a></p>
	</div>
  </div>
</div>

<ul class="nav nav-tabs" style="margin-bottom:20px;">
    <li class="{if $billmysales_tab === 'configuracion'}active{/if}">
        <a href="{$billmysales_tab_url|escape:'html':'UTF-8'}&billmysales_tab=configuracion">
            <i class="icon icon-cogs"></i> {l s='Configuración' mod='billmysales'}
        </a>
    </li>
    <li class="{if $billmysales_tab === 'campos'}active{/if}">
        <a href="{$billmysales_tab_url|escape:'html':'UTF-8'}&billmysales_tab=campos">
            <i class="icon icon-list-alt"></i> {l s='Campos personalizados' mod='billmysales'}
        </a>
    </li>
</ul>

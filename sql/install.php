<?php

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
 */

// La mayor parte de la configuración del módulo se guarda con
// Configuration::updateValue()/deleteByName() (ver install()/uninstall()
// en billmysales.php). Esta tabla es la única excepción: guarda los
// valores de los campos personalizados que se agregan al formulario de
// dirección del checkout (ver hookAdditionalCustomerAddressFields()).

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'billmysales_address_field` (
    `id_address` INT UNSIGNED NOT NULL,
    `field_key` VARCHAR(64) NOT NULL,
    `field_value` VARCHAR(255) NOT NULL DEFAULT \'\',
    PRIMARY KEY (`id_address`, `field_key`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

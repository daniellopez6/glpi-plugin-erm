<?php

/**
 * -------------------------------------------------------------------------
 * ERM - Enterprise Risk Management plugin for GLPI
 * Copyright (C) 2026 daniellopez6
 * https://github.com/daniellopez6/glpi-plugin-erm
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

/**
 * Plugin ERM - Gestión de Riesgos Corporativos para GLPI.
 * Registro de riesgos empresariales (ISO 31000 / COSO ERM) integrado con GLPI GRC Manager.
 * Licencia GPL-3.0-or-later.
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Erm\Action;
use GlpiPlugin\Erm\Category;
use GlpiPlugin\Erm\Dashboard;
use GlpiPlugin\Erm\Indicator;
use GlpiPlugin\Erm\Profile;
use GlpiPlugin\Erm\Risk;
use GlpiPlugin\Erm\Risk_Item;

define('PLUGIN_ERM_VERSION', '1.1.1');
define('PLUGIN_ERM_MIN_GLPI', '12.0.0');
define('PLUGIN_ERM_MAX_GLPI', '12.99.99');

function plugin_init_erm(): void
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['erm'] = true;

    if (!Plugin::isPluginActive('erm')) {
        return;
    }

    Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);
    Plugin::registerClass(Action::class, ['addtabon' => [Risk::class]]);
    Plugin::registerClass(Indicator::class, ['addtabon' => [Risk::class]]);
    Plugin::registerClass(Risk_Item::class, ['addtabon' => array_merge([Risk::class], Risk_Item::getLinkableItemtypes())]);
    Plugin::registerClass(Risk::class, ['document_types' => true]);
    $CFG_GLPI['document_types'][] = Risk::class;

    // Mismo sector de menú que GRC Manager si está activo; si no, en "Gestión".
    $sector = Plugin::isPluginActive('grcmanager') ? 'grcmanager' : 'management';
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['erm'] = [$sector => [Dashboard::class, Risk::class, Category::class]];
}

function plugin_version_erm(): array
{
    return [
        'name'         => 'Gestión de Riesgos Corporativos (ERM)',
        'version'      => PLUGIN_ERM_VERSION,
        'author'       => 'daniellopez6',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/daniellopez6/glpi-plugin-erm',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_ERM_MIN_GLPI, 'max' => PLUGIN_ERM_MAX_GLPI],
            'php'  => ['min' => '8.2'],
        ],
    ];
}

/**
 * Opciones del plugin: no ejecutar la instalación automáticamente al descargarlo desde el Marketplace.
 *
 * @return array<string,mixed>
 */
function plugin_erm_options(): array
{
    return [Plugin::OPTION_AUTOINSTALL_DISABLED => true];
}

function plugin_erm_check_prerequisites(): bool
{
    return true;
}

function plugin_erm_check_config($verbose = false): bool
{
    return true;
}

/** URL de un archivo del plugin (relativa a la raíz de GLPI o absoluta). */
function plugin_erm_url(string $path, bool $full = true): string
{
    global $CFG_GLPI;
    return ($full ? $CFG_GLPI['root_doc'] : '') . '/plugins/erm/' . ltrim($path, '/');
}

/** Sector de menú donde viven las páginas del plugin (el de GRC Manager si está activo). */
function plugin_erm_sector(): string
{
    return Plugin::isPluginActive('grcmanager') ? 'grcmanager' : 'management';
}

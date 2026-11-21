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
 * ERM - Enterprise Risk Management plugin for GLPI.
 * Enterprise risk register (ISO 31000 / COSO ERM), optionally integrated with GLPI GRC Manager.
 * Licencia GPL-3.0-or-later.
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Erm\Action;
use GlpiPlugin\Erm\Category;
use GlpiPlugin\Erm\Consolidated;
use GlpiPlugin\Erm\Dashboard;
use GlpiPlugin\Erm\EntityConfig;
use GlpiPlugin\Erm\Event;
use GlpiPlugin\Erm\Event_Item;
use GlpiPlugin\Erm\Indicator;
use GlpiPlugin\Erm\MySpace;
use GlpiPlugin\Erm\Profile;
use GlpiPlugin\Erm\Report;
use GlpiPlugin\Erm\Review;
use GlpiPlugin\Erm\Risk;
use GlpiPlugin\Erm\Risk_Item;

define('PLUGIN_ERM_VERSION', '1.9.1');
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
    Plugin::registerClass(Risk::class, ['document_types' => true, 'notificationtemplates_types' => true]);
    Plugin::registerClass(Review::class, ['addtabon' => [Risk::class]]);
    Plugin::registerClass(EntityConfig::class, ['addtabon' => ['Entity']]);
    Plugin::registerClass(Event::class, ['document_types' => true]);
    Plugin::registerClass(Event_Item::class, ['addtabon' => array_merge([Event::class], Event_Item::getLinkableItemtypes())]);
    $CFG_GLPI['document_types'][] = Risk::class;
    $CFG_GLPI['document_types'][] = Event::class;

    // Same menu sector as GRC Manager when active; otherwise "Management".
    $sector = Plugin::isPluginActive('grcmanager') ? 'grcmanager' : 'management';
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['erm'] = [$sector => [Dashboard::class, MySpace::class, Consolidated::class, Risk::class, Action::class, Event::class, Report::class, Category::class]];
}

function plugin_version_erm(): array
{
    return [
        'name'         => __('ERM - Enterprise Risk Management', 'erm'),
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
 * Plugin options: do not run the installation automatically when downloaded from the Marketplace.
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

/** URL of a plugin file (relative to the GLPI root or absolute). */
function plugin_erm_url(string $path, bool $full = true): string
{
    global $CFG_GLPI;
    return ($full ? $CFG_GLPI['root_doc'] : '') . '/plugins/erm/' . ltrim($path, '/');
}

/** Menu sector of the plugin pages (GRC Manager's one when active). */
function plugin_erm_sector(): string
{
    return Plugin::isPluginActive('grcmanager') ? 'grcmanager' : 'management';
}

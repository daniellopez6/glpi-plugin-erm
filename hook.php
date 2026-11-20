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
 * Installation and uninstallation of the ERM plugin.
 */

use GlpiPlugin\Erm\Category;
use GlpiPlugin\Erm\Rights;
use GlpiPlugin\Erm\Risk;

function plugin_erm_install(): bool
{
    global $DB;

    $migration = new Migration(PLUGIN_ERM_VERSION);
    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    $tables = [
        'glpi_plugin_erm_categories' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `completename` text,
            `plugin_erm_categories_id` int {$sign} NOT NULL DEFAULT '0',
            `level` int NOT NULL DEFAULT '0',
            `ancestors_cache` longtext,
            `sons_cache` longtext,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '1',
            `comment` text,
            `appetite_level` varchar(16) NOT NULL DEFAULT '',
            `tolerance_level` varchar(16) NOT NULL DEFAULT '',
            `appetite_statement` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `plugin_erm_categories_id` (`plugin_erm_categories_id`),
            KEY `entities_id` (`entities_id`)",
        'glpi_plugin_erm_risks' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '1',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `code` varchar(32) NOT NULL DEFAULT '',
            `name` varchar(255) NOT NULL DEFAULT '',
            `description` text,
            `plugin_erm_categories_id` int {$sign} NOT NULL DEFAULT '0',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `groups_id` int {$sign} NOT NULL DEFAULT '0',
            `causes` text,
            `consequences` text,
            `inherent_probability` tinyint NOT NULL DEFAULT '0',
            `inherent_impact` tinyint NOT NULL DEFAULT '0',
            `inherent_score` tinyint NOT NULL DEFAULT '0',
            `inherent_level` varchar(16) NOT NULL DEFAULT '',
            `existing_controls` text,
            `control_effectiveness` varchar(16) NOT NULL DEFAULT '',
            `residual_probability` tinyint NOT NULL DEFAULT '0',
            `residual_impact` tinyint NOT NULL DEFAULT '0',
            `residual_score` tinyint NOT NULL DEFAULT '0',
            `residual_level` varchar(16) NOT NULL DEFAULT '',
            `economic_impact_min` decimal(20,2) DEFAULT NULL,
            `economic_impact_max` decimal(20,2) DEFAULT NULL,
            `velocity` varchar(16) NOT NULL DEFAULT '',
            `trend` varchar(16) NOT NULL DEFAULT '',
            `treatment` varchar(16) NOT NULL DEFAULT '',
            `status` varchar(16) NOT NULL DEFAULT 'identified',
            `justification` text,
            `users_id_acceptance` int {$sign} NOT NULL DEFAULT '0',
            `date_acceptance` timestamp NULL DEFAULT NULL,
            `review_date` date DEFAULT NULL,
            `last_review_date` date DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `plugin_erm_categories_id` (`plugin_erm_categories_id`),
            KEY `users_id` (`users_id`),
            KEY `groups_id` (`groups_id`),
            KEY `status` (`status`),
            KEY `is_deleted` (`is_deleted`)",
        'glpi_plugin_erm_actions' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_erm_risks_id` int {$sign} NOT NULL DEFAULT '0',
            `name` text,
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `due_date` date DEFAULT NULL,
            `status` varchar(16) NOT NULL DEFAULT 'planned',
            `completion_date` date DEFAULT NULL,
            `cost` decimal(20,2) DEFAULT NULL,
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_erm_risks_id` (`plugin_erm_risks_id`),
            KEY `users_id` (`users_id`)",
        'glpi_plugin_erm_indicators' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_erm_risks_id` int {$sign} NOT NULL DEFAULT '0',
            `name` varchar(255) NOT NULL DEFAULT '',
            `unit` varchar(32) NOT NULL DEFAULT '',
            `direction` varchar(20) NOT NULL DEFAULT 'higher_is_worse',
            `threshold_warning` decimal(20,2) DEFAULT NULL,
            `threshold_critical` decimal(20,2) DEFAULT NULL,
            `frequency` varchar(16) NOT NULL DEFAULT 'monthly',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_erm_risks_id` (`plugin_erm_risks_id`)",
        'glpi_plugin_erm_indicatorvalues' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_erm_indicators_id` int {$sign} NOT NULL DEFAULT '0',
            `value_date` date DEFAULT NULL,
            `value` decimal(20,2) NOT NULL DEFAULT '0.00',
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_erm_indicators_id` (`plugin_erm_indicators_id`)",
        'glpi_plugin_erm_risks_items' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_erm_risks_id` int {$sign} NOT NULL DEFAULT '0',
            `itemtype` varchar(255) NOT NULL DEFAULT '',
            `items_id` int {$sign} NOT NULL DEFAULT '0',
            `comment` varchar(255) NOT NULL DEFAULT '',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_erm_risks_id`,`itemtype`,`items_id`),
            KEY `item` (`itemtype`,`items_id`)",
    ];
    foreach ($tables as $table => $columns) {
        if (!$DB->tableExists($table)) {
            // Static DDL defined in this file (no user data). GLPI 12 provides no API to create
            // tables; official plugins use the same mechanism.
            $DB->doQuery("CREATE TABLE `$table` ($columns) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
        }
    }

    // Rights (1.1.0): register, treatment, configuration and acceptance. Only missing rights are created;
    // values already set by an administrator are never overwritten.
    foreach (Rights::all() as $right) {
        if (countElementsInTable('glpi_profilerights', ['name' => $right]) === 0) {
            ProfileRight::addProfileRights([$right]);
            foreach ($DB->request(['FROM' => 'glpi_profiles']) as $profile) {
                $value = Rights::defaults((string) $profile['name'])[$right] ?? 0;
                if ($right === Rights::RISK && $profile['interface'] !== 'central') {
                    $value = 0;
                }
                if ($value) {
                    ProfileRight::updateProfileRights((int) $profile['id'], [$right => $value]);
                }
            }
        }
    }

    // Default columns of the risk register (code, category, owner, levels, treatment, status, review)
    if (countElementsInTable('glpi_displaypreferences', ['itemtype' => 'GlpiPlugin\\Erm\\Risk', 'users_id' => 0]) === 0) {
        foreach ([3, 4, 5, 7, 8, 13, 11, 12, 17] as $rank => $num) {
            $DB->insert('glpi_displaypreferences', ['itemtype' => 'GlpiPlugin\\Erm\\Risk', 'num' => $num, 'rank' => $rank + 1,
                                                   'users_id' => 0, 'interface' => 'central']);
        }
    }

    if (countElementsInTable(Category::getTable()) === 0) {
        Category::createDefaultTaxonomy();
    }

    $migration->executeMigration();
    return true;
}

function plugin_erm_uninstall(): bool
{
    global $DB;

    foreach (
        ['glpi_plugin_erm_categories', 'glpi_plugin_erm_risks', 'glpi_plugin_erm_actions', 'glpi_plugin_erm_indicators',
              'glpi_plugin_erm_indicatorvalues', 'glpi_plugin_erm_risks_items'] as $table
    ) {
        $DB->dropTable($table, true);
    }
    ProfileRight::deleteProfileRights(Rights::all());
    foreach (['GlpiPlugin\\Erm\\Risk', 'GlpiPlugin\\Erm\\Category'] as $itemtype) {
        $DB->delete('glpi_displaypreferences', ['itemtype' => $itemtype]);
        $DB->delete('glpi_documents_items', ['itemtype' => $itemtype]);
        $DB->delete('glpi_logs', ['itemtype' => $itemtype]);
    }
    return true;
}

/**
 * Search: a profile with "See own" (without "See all") only lists its own risks.
 */
function plugin_erm_addDefaultWhere($itemtype)
{
    if ($itemtype === Risk::class && Rights::onlyOwn()) {
        return [Rights::ownCriteria(Risk::getTable())];
    }
    return [];
}

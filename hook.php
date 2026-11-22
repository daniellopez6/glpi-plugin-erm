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

use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;
use GlpiPlugin\Erm\Action;
use GlpiPlugin\Erm\AcceptanceState;
use GlpiPlugin\Erm\AiImpact;
use GlpiPlugin\Erm\Category;
use GlpiPlugin\Erm\Indicator;
use GlpiPlugin\Erm\NotificationTargetRisk;
use GlpiPlugin\Erm\Notifier;
use GlpiPlugin\Erm\Profile as ErmProfile;
use GlpiPlugin\Erm\Rights;
use GlpiPlugin\Erm\Snapshot;
use GlpiPlugin\Erm\Risk;

function plugin_erm_install(): bool
{
    global $DB, $CFG_GLPI;

    // From the command line, create texts (taxonomy, profiles, notifications) in the default language of GLPI
    if (isCommandLine() && !empty($CFG_GLPI['language'])) {
        Session::loadLanguage($CFG_GLPI['language']);
        Plugin::loadLang('erm', $CFG_GLPI['language']);
    }

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
            `erm_key` varchar(32) NOT NULL DEFAULT '',
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
            `target_probability` tinyint NOT NULL DEFAULT '0',
            `target_impact` tinyint NOT NULL DEFAULT '0',
            `target_score` tinyint NOT NULL DEFAULT '0',
            `target_level` varchar(16) NOT NULL DEFAULT '',
            `target_date` date DEFAULT NULL,
            `economic_impact_min` decimal(20,2) DEFAULT NULL,
            `economic_impact_max` decimal(20,2) DEFAULT NULL,
            `velocity` varchar(16) NOT NULL DEFAULT '',
            `trend` varchar(16) NOT NULL DEFAULT '',
            `treatment` varchar(16) NOT NULL DEFAULT '',
            `status` varchar(16) NOT NULL DEFAULT 'identified',
            `justification` text,
            `users_id_acceptance` int {$sign} NOT NULL DEFAULT '0',
            `date_acceptance` timestamp NULL DEFAULT NULL,
            `acceptance_valid_until` date DEFAULT NULL,
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
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '1',
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
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`),
            KEY `due_date` (`due_date`)",
        'glpi_plugin_erm_indicators' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_erm_risks_id` int {$sign} NOT NULL DEFAULT '0',
            `name` varchar(255) NOT NULL DEFAULT '',
            `unit` varchar(32) NOT NULL DEFAULT '',
            `direction` varchar(20) NOT NULL DEFAULT 'higher_is_worse',
            `threshold_warning` decimal(20,2) DEFAULT NULL,
            `threshold_critical` decimal(20,2) DEFAULT NULL,
            `frequency` varchar(16) NOT NULL DEFAULT 'monthly',
            `source` varchar(16) NOT NULL DEFAULT 'manual',
            `savedsearches_id` int {$sign} NOT NULL DEFAULT '0',
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
        'glpi_plugin_erm_entityconfigs' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `code_prefix` varchar(16) NOT NULL DEFAULT '',
            `impact_2` decimal(20,2) DEFAULT NULL,
            `impact_3` decimal(20,2) DEFAULT NULL,
            `impact_4` decimal(20,2) DEFAULT NULL,
            `impact_5` decimal(20,2) DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entities_id` (`entities_id`)",
        'glpi_plugin_erm_appetites' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `plugin_erm_categories_id` int {$sign} NOT NULL DEFAULT '0',
            `appetite_level` varchar(16) NOT NULL DEFAULT '',
            `tolerance_level` varchar(16) NOT NULL DEFAULT '',
            `appetite_statement` text,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`entities_id`,`plugin_erm_categories_id`),
            KEY `plugin_erm_categories_id` (`plugin_erm_categories_id`)",
        'glpi_plugin_erm_events' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `name` varchar(255) NOT NULL DEFAULT '',
            `description` text,
            `root_cause` text,
            `event_type` varchar(16) NOT NULL DEFAULT 'loss',
            `status` varchar(16) NOT NULL DEFAULT 'open',
            `occurrence_date` date DEFAULT NULL,
            `detection_date` date DEFAULT NULL,
            `plugin_erm_categories_id` int {$sign} NOT NULL DEFAULT '0',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `gross_loss` decimal(20,2) DEFAULT NULL,
            `recoveries` decimal(20,2) DEFAULT NULL,
            `net_loss` decimal(20,2) DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_deleted` (`is_deleted`),
            KEY `occurrence_date` (`occurrence_date`),
            KEY `plugin_erm_categories_id` (`plugin_erm_categories_id`),
            KEY `users_id` (`users_id`)",
        'glpi_plugin_erm_events_items' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_erm_events_id` int {$sign} NOT NULL DEFAULT '0',
            `itemtype` varchar(255) NOT NULL DEFAULT '',
            `items_id` int {$sign} NOT NULL DEFAULT '0',
            `comment` varchar(255) NOT NULL DEFAULT '',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_erm_events_id`,`itemtype`,`items_id`),
            KEY `item` (`itemtype`,`items_id`)",
        'glpi_plugin_erm_reviews' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_erm_risks_id` int {$sign} NOT NULL DEFAULT '0',
            `review_date` date DEFAULT NULL,
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `participants` text,
            `conclusion` varchar(16) NOT NULL DEFAULT 'unchanged',
            `comment` text,
            `inherent_probability` tinyint NOT NULL DEFAULT '0',
            `inherent_impact` tinyint NOT NULL DEFAULT '0',
            `inherent_score` tinyint NOT NULL DEFAULT '0',
            `inherent_level` varchar(16) NOT NULL DEFAULT '',
            `residual_probability` tinyint NOT NULL DEFAULT '0',
            `residual_impact` tinyint NOT NULL DEFAULT '0',
            `residual_score` tinyint NOT NULL DEFAULT '0',
            `residual_level` varchar(16) NOT NULL DEFAULT '',
            `target_score` tinyint NOT NULL DEFAULT '0',
            `target_level` varchar(16) NOT NULL DEFAULT '',
            `status` varchar(16) NOT NULL DEFAULT '',
            `treatment` varchar(16) NOT NULL DEFAULT '',
            `next_review_date` date DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_erm_risks_id` (`plugin_erm_risks_id`),
            KEY `review_date` (`review_date`)",
        'glpi_plugin_erm_alerts' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `itemtype` varchar(255) NOT NULL DEFAULT '',
            `items_id` int {$sign} NOT NULL DEFAULT '0',
            `alert_type` varchar(32) NOT NULL DEFAULT '',
            `date_sent` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`itemtype`,`items_id`,`alert_type`)",
        'glpi_plugin_erm_snapshots' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `period` varchar(7) NOT NULL DEFAULT '',
            `snapshot_date` timestamp NULL DEFAULT NULL,
            `plugin_erm_risks_id` int {$sign} NOT NULL DEFAULT '0',
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `code` varchar(32) NOT NULL DEFAULT '',
            `name` varchar(255) NOT NULL DEFAULT '',
            `category` text,
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `inherent_score` tinyint NOT NULL DEFAULT '0',
            `inherent_level` varchar(16) NOT NULL DEFAULT '',
            `residual_score` tinyint NOT NULL DEFAULT '0',
            `residual_level` varchar(16) NOT NULL DEFAULT '',
            `target_score` tinyint NOT NULL DEFAULT '0',
            `target_level` varchar(16) NOT NULL DEFAULT '',
            `appetite_position` varchar(20) NOT NULL DEFAULT '',
            `economic_impact_max` decimal(20,2) DEFAULT NULL,
            `status` varchar(16) NOT NULL DEFAULT '',
            `treatment` varchar(16) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`period`,`plugin_erm_risks_id`),
            KEY `entities_id` (`entities_id`)",
        'glpi_plugin_erm_aisystems' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `name` varchar(255) NOT NULL DEFAULT '',
            `purpose` text,
            `ai_type` varchar(32) NOT NULL DEFAULT 'generative',
            `deployment` varchar(32) NOT NULL DEFAULT 'saas_api',
            `suppliers_id` int {$sign} NOT NULL DEFAULT '0',
            `model_name` varchar(255) NOT NULL DEFAULT '',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `groups_id` int {$sign} NOT NULL DEFAULT '0',
            `personal_data` tinyint NOT NULL DEFAULT '0',
            `sensitive_data` tinyint NOT NULL DEFAULT '0',
            `affected_groups` varchar(255) NOT NULL DEFAULT '',
            `human_oversight` varchar(16) NOT NULL DEFAULT 'on_loop',
            `data_description` text,
            `aiact_role` varchar(16) NOT NULL DEFAULT 'deployer',
            `aiact_class` varchar(16) NOT NULL DEFAULT 'unassessed',
            `status` varchar(16) NOT NULL DEFAULT 'planned',
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_deleted` (`is_deleted`),
            KEY `users_id` (`users_id`),
            KEY `suppliers_id` (`suppliers_id`)",
        'glpi_plugin_erm_aiassessments' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_erm_aisystems_id` int {$sign} NOT NULL DEFAULT '0',
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            `assessment_date` date DEFAULT NULL,
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `status` varchar(16) NOT NULL DEFAULT 'draft',
            " . implode('', array_map(fn($d) => "`score_{$d}` tinyint NOT NULL DEFAULT '0', `notes_{$d}` text, ", AiImpact::DIMENSIONS)) . "
            `overall_level` varchar(16) NOT NULL DEFAULT '',
            `mitigations` text,
            `residual_level` varchar(16) NOT NULL DEFAULT '',
            `next_assessment_date` date DEFAULT NULL,
            `editors` text,
            `users_id_approval` int {$sign} NOT NULL DEFAULT '0',
            `date_approval` timestamp NULL DEFAULT NULL,
            `approval_comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_erm_aisystems_id` (`plugin_erm_aisystems_id`),
            KEY `entities_id` (`entities_id`),
            KEY `status` (`status`)",
        'glpi_plugin_erm_concerns' => "
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `report_type` varchar(16) NOT NULL DEFAULT 'risk',
            `name` varchar(255) NOT NULL DEFAULT '',
            `description` text,
            `area` varchar(255) NOT NULL DEFAULT '',
            `occurrence_date` date DEFAULT NULL,
            `estimated_loss` decimal(20,2) DEFAULT NULL,
            `status` varchar(16) NOT NULL DEFAULT 'new',
            `users_id_reviewer` int {$sign} NOT NULL DEFAULT '0',
            `date_review` timestamp NULL DEFAULT NULL,
            `review_comment` text,
            `result_itemtype` varchar(255) NOT NULL DEFAULT '',
            `result_items_id` int {$sign} NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`),
            KEY `status` (`status`)",
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

    // 1.4.0: actions carry the entity of their risk so the action register can be searched per entity
    if (!$DB->fieldExists('glpi_plugin_erm_actions', 'entities_id')) {
        $migration->addField('glpi_plugin_erm_actions', 'entities_id', "int {$sign} NOT NULL DEFAULT '0'", ['after' => 'plugin_erm_risks_id']);
        $migration->addField('glpi_plugin_erm_actions', 'is_recursive', "tinyint NOT NULL DEFAULT '1'", ['after' => 'entities_id']);
        $migration->addKey('glpi_plugin_erm_actions', 'entities_id');
        $migration->addKey('glpi_plugin_erm_actions', 'due_date');
        $migration->migrationOneTable('glpi_plugin_erm_actions');
        $DB->update('glpi_plugin_erm_actions', [
            'glpi_plugin_erm_actions.entities_id'  => new QueryExpression($DB->quoteName('r.entities_id')),
            'glpi_plugin_erm_actions.is_recursive' => new QueryExpression($DB->quoteName('r.is_recursive')),
        ], ['r.id' => ['>', 0]], ['INNER JOIN' => ['glpi_plugin_erm_risks AS r' => ['ON' => ['glpi_plugin_erm_actions' => 'plugin_erm_risks_id', 'r' => 'id']]]]);
    }

    // 2.1.0: internal key of the system categories (the 2.0.x marker in the comment was visible to users)
    if (!$DB->fieldExists('glpi_plugin_erm_categories', 'erm_key')) {
        $migration->addField('glpi_plugin_erm_categories', 'erm_key', "varchar(32) NOT NULL DEFAULT ''");
        $migration->migrationOneTable('glpi_plugin_erm_categories');
        $DB->update('glpi_plugin_erm_categories', [
            'erm_key' => 'ai',
            'comment' => __('Risks of the AI systems of the organization (ISO/IEC 42001, AI Act).', 'erm'),
        ], ['comment' => ['LIKE', '%' . Category::AI_MARKER . '%']]);
    }

    // 2.0.0: editors of AI impact assessments (segregation of duties)
    if ($DB->tableExists('glpi_plugin_erm_aiassessments') && !$DB->fieldExists('glpi_plugin_erm_aiassessments', 'editors')) {
        $migration->addField('glpi_plugin_erm_aiassessments', 'editors', 'text', ['after' => 'next_assessment_date']);
        $migration->migrationOneTable('glpi_plugin_erm_aiassessments');
    }
    // 2.0.1: the list of editors must never be truncated
    if ($DB->tableExists('glpi_plugin_erm_aiassessments')) {
        $type = $DB->request(['SELECT' => 'DATA_TYPE', 'FROM' => 'information_schema.COLUMNS', 'WHERE' => [
            'TABLE_SCHEMA' => $DB->dbdefault, 'TABLE_NAME' => 'glpi_plugin_erm_aiassessments', 'COLUMN_NAME' => 'editors',
        ]])->current()['DATA_TYPE'] ?? '';
        if ($type === 'varchar') {
            $migration->changeField('glpi_plugin_erm_aiassessments', 'editors', 'editors', 'text');
            $migration->migrationOneTable('glpi_plugin_erm_aiassessments');
        }
    }

    // 1.9.0: acceptance validity and automatic KRIs
    if (!$DB->fieldExists('glpi_plugin_erm_risks', 'acceptance_valid_until')) {
        $migration->addField('glpi_plugin_erm_risks', 'acceptance_valid_until', 'date DEFAULT NULL', ['after' => 'date_acceptance']);
        $migration->migrationOneTable('glpi_plugin_erm_risks');
        // Existing acceptances get the default validity from their acceptance date
        $DB->doQuery("UPDATE `glpi_plugin_erm_risks` SET `acceptance_valid_until` = DATE_ADD(DATE(`date_acceptance`), INTERVAL "
            . AcceptanceState::VALIDITY_MONTHS . " MONTH) WHERE `status` = 'accepted' AND `date_acceptance` IS NOT NULL");
    }
    foreach (['source' => "varchar(16) NOT NULL DEFAULT 'manual'", 'savedsearches_id' => "int {$sign} NOT NULL DEFAULT '0'"] as $field => $def) {
        if (!$DB->fieldExists('glpi_plugin_erm_indicators', $field)) {
            $migration->addField('glpi_plugin_erm_indicators', $field, $def, ['after' => 'frequency']);
        }
    }
    $migration->migrationOneTable('glpi_plugin_erm_indicators');

    // 1.6.0: target risk
    foreach (
        ['target_probability' => "tinyint NOT NULL DEFAULT '0'", 'target_impact' => "tinyint NOT NULL DEFAULT '0'",
              'target_score' => "tinyint NOT NULL DEFAULT '0'", 'target_level' => "varchar(16) NOT NULL DEFAULT ''",
        'target_date' => 'date DEFAULT NULL'] as $field => $def
    ) {
        if (!$DB->fieldExists('glpi_plugin_erm_risks', $field)) {
            $migration->addField('glpi_plugin_erm_risks', $field, $def, ['after' => 'residual_level']);
        }
    }
    $migration->migrationOneTable('glpi_plugin_erm_risks');

    // 1.5.0: economic impact criteria per entity
    foreach ([2, 3, 4, 5] as $lvl) {
        if (!$DB->fieldExists('glpi_plugin_erm_entityconfigs', "impact_{$lvl}")) {
            $migration->addField('glpi_plugin_erm_entityconfigs', "impact_{$lvl}", 'decimal(20,2) DEFAULT NULL');
        }
    }
    $migration->migrationOneTable('glpi_plugin_erm_entityconfigs');

    // Rights (1.1.0): register, treatment, configuration and acceptance. Only missing rights are created;
    // values already set by an administrator are never overwritten.
    foreach (Rights::all() as $right) {
        if (countElementsInTable('glpi_profilerights', ['name' => $right]) === 0) {
            ProfileRight::addProfileRights([$right]);
            $roles = ErmProfile::roleProfiles();
            foreach ($DB->request(['FROM' => 'glpi_profiles']) as $profile) {
                $value = Rights::defaults((string) $profile['name'])[$right] ?? 0;
                // Role profiles created by the plugin get the value of their role definition
                foreach ($roles as $key => $role) {
                    if (str_contains((string) $profile['comment'], ErmProfile::marker($key))) {
                        $value = $role['rights'][$right] ?? 0;
                    }
                }
                if ($right === Rights::RISK && $profile['interface'] !== 'central') {
                    $value = 0;
                }
                if ($value) {
                    ProfileRight::updateProfileRights((int) $profile['id'], [$right => $value]);
                }
            }
        }
    }

    // Default columns of the action register (risk code, risk, responsible, due date, status, done on)
    if (countElementsInTable('glpi_displaypreferences', ['itemtype' => 'GlpiPlugin\\Erm\\Action', 'users_id' => 0]) === 0) {
        foreach ([3, 6, 7, 4, 5, 8, 11] as $rank => $num) {
            $DB->insert('glpi_displaypreferences', ['itemtype' => 'GlpiPlugin\\Erm\\Action', 'num' => $num, 'rank' => $rank + 1,
                                                   'users_id' => 0, 'interface' => 'central']);
        }
    }

    // Default columns of the loss event register (date, type, category, net loss, status, reported by)
    if (countElementsInTable('glpi_displaypreferences', ['itemtype' => 'GlpiPlugin\\Erm\\Event', 'users_id' => 0]) === 0) {
        foreach ([3, 5, 10, 9, 6, 11] as $rank => $num) {
            $DB->insert('glpi_displaypreferences', ['itemtype' => 'GlpiPlugin\\Erm\\Event', 'num' => $num, 'rank' => $rank + 1,
                                                   'users_id' => 0, 'interface' => 'central']);
        }
    }

    // Default columns of the AI system register (status, type, AI Act class, provider, owner, personal data)
    if (countElementsInTable('glpi_displaypreferences', ['itemtype' => 'GlpiPlugin\\Erm\\AiSystem', 'users_id' => 0]) === 0) {
        foreach ([4, 3, 5, 6, 7, 10] as $rank => $num) {
            $DB->insert('glpi_displaypreferences', ['itemtype' => 'GlpiPlugin\\Erm\\AiSystem', 'num' => $num, 'rank' => $rank + 1,
                                                   'users_id' => 0, 'interface' => 'central']);
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
    } else {
        // 2.0.0: "Artificial intelligence" branch for existing installations
        Category::createAiTaxonomy();
    }

    // 1.7.0: ready-to-assign role profiles (created once, never overwritten)
    ErmProfile::createRoleProfiles();

    // 1.6.0: daily e-mail alerts and assignment notices
    CronTask::register(Notifier::class, 'ermalerts', DAY_TIMESTAMP, [
        'mode'    => CronTask::MODE_EXTERNAL,
        'state'   => CronTask::STATE_WAITING,
        'hourmin' => 6,
        'hourmax' => 8,
        'comment' => '',
    ]);
    plugin_erm_install_notifications();
    CronTask::register(Indicator::class, 'ermkri', DAY_TIMESTAMP, [
        'mode'    => CronTask::MODE_EXTERNAL,
        'state'   => CronTask::STATE_WAITING,
        'hourmin' => 1,
        'hourmax' => 5,
        'comment' => '',
    ]);
    CronTask::register(Snapshot::class, 'ermsnapshot', DAY_TIMESTAMP, [
        'mode'    => CronTask::MODE_EXTERNAL,
        'state'   => CronTask::STATE_WAITING,
        'hourmin' => 2,
        'hourmax' => 5,
        'comment' => '',
    ]);

    $migration->executeMigration();
    return true;
}

/**
 * Creates the notification templates and notifications of the plugin (once; never overwrites admin changes).
 * Texts are created in the language of the user running the installation.
 */
function plugin_erm_install_notifications(): void
{
    global $DB;

    $now = date('Y-m-d H:i:s');
    $itemtype = Risk::class;
    $alertsText = "##FOREACHalerts##\n- ##alert.type##: ##alert.label## ##IFalert.date##(##alert.date##)##ENDIFalert.date## ##IFalert.responsible##- ##alert.responsible####ENDIFalert.responsible##\n##ENDFOREACHalerts##";
    $alertsHtml = '<ul>##FOREACHalerts##<li><b>##alert.type##</b>: ##alert.label## ##IFalert.date##(##alert.date##)##ENDIFalert.date## '
        . '##IFalert.responsible##- ##alert.responsible####ENDIFalert.responsible##</li>##ENDFOREACHalerts##</ul>';
    $header = "##erm.code## ##erm.name##\n##lang.erm.entity##: ##erm.entity##\n##lang.erm.owner##: ##erm.owner##\n\n";
    $headerHtml = '<p><b>##erm.code## ##erm.name##</b><br>##lang.erm.entity##: ##erm.entity##<br>##lang.erm.owner##: ##erm.owner##</p>';
    $footer = "\n##erm.url##";
    $footerHtml = '<p><a href="##erm.url##">##erm.url##</a></p>';

    $definitions = [
        NotificationTargetRisk::EVENT => [
            'name'    => __('Corporate risk alerts', 'erm'),
            'subject' => '##erm.code## - ' . __('Corporate risk alerts', 'erm') . ' (##erm.alerts.number##)',
            'targets' => [NotificationTargetRisk::RISK_OWNER, NotificationTargetRisk::ACTION_RESPONSIBLES, NotificationTargetRisk::KRI_RESPONSIBLES],
        ],
        NotificationTargetRisk::EVENT_OWNER => [
            'name'    => __('A corporate risk has been assigned to you', 'erm'),
            'subject' => __('A corporate risk has been assigned to you', 'erm') . ': ##erm.code##',
            'targets' => [NotificationTargetRisk::RISK_OWNER],
        ],
        NotificationTargetRisk::EVENT_ACTION_DONE => [
            'name'    => __('A treatment action of your risk has been completed', 'erm'),
            'subject' => __('A treatment action of your risk has been completed', 'erm') . ' (##erm.code##)',
            'targets' => [NotificationTargetRisk::RISK_OWNER],
        ],
        NotificationTargetRisk::EVENT_ACTION => [
            'name'    => __('A treatment action has been assigned to you', 'erm'),
            'subject' => __('A treatment action has been assigned to you', 'erm') . ' (##erm.code##)',
            'targets' => [NotificationTargetRisk::ACTION_RESPONSIBLES],
        ],
    ];
    foreach ($definitions as $event => $def) {
        if (countElementsInTable('glpi_notifications', ['itemtype' => $itemtype, 'event' => $event]) > 0) {
            continue;
        }
        $name = 'ERM - ' . $def['name'];
        $DB->insert('glpi_notificationtemplates', ['name' => $name, 'itemtype' => $itemtype, 'date_mod' => $now, 'date_creation' => $now]);
        $templateId = $DB->insertId();
        $DB->insert('glpi_notificationtemplatetranslations', [
            'notificationtemplates_id' => $templateId, 'language' => '', 'subject' => $def['subject'],
            'content_text' => $header . $alertsText . $footer,
            'content_html' => $headerHtml . $alertsHtml . $footerHtml,
        ]);
        $DB->insert('glpi_notifications', ['name' => $name, 'entities_id' => 0, 'itemtype' => $itemtype, 'event' => $event,
                                          'is_recursive' => 1, 'is_active' => 1, 'date_mod' => $now, 'date_creation' => $now]);
        $notificationId = $DB->insertId();
        $DB->insert('glpi_notifications_notificationtemplates', ['notifications_id' => $notificationId, 'mode' => 'mailing',
                                                                'notificationtemplates_id' => $templateId]);
        foreach ($def['targets'] as $target) {
            $DB->insert('glpi_notificationtargets', ['items_id' => $target, 'type' => Notification::USER_TYPE, 'notifications_id' => $notificationId]);
        }
    }
}

function plugin_erm_uninstall(): bool
{
    global $DB;

    CronTask::unregister('erm');
    foreach ($DB->request(['FROM' => 'glpi_notifications', 'WHERE' => ['itemtype' => Risk::class]]) as $n) {
        $DB->delete('glpi_notificationtargets', ['notifications_id' => $n['id']]);
        $DB->delete('glpi_notifications_notificationtemplates', ['notifications_id' => $n['id']]);
    }
    $DB->delete('glpi_notifications', ['itemtype' => Risk::class]);
    foreach ($DB->request(['FROM' => 'glpi_notificationtemplates', 'WHERE' => ['itemtype' => Risk::class]]) as $t) {
        $DB->delete('glpi_notificationtemplatetranslations', ['notificationtemplates_id' => $t['id']]);
    }
    $DB->delete('glpi_notificationtemplates', ['itemtype' => Risk::class]);

    foreach (
        ['glpi_plugin_erm_categories', 'glpi_plugin_erm_risks', 'glpi_plugin_erm_actions', 'glpi_plugin_erm_indicators',
              'glpi_plugin_erm_indicatorvalues', 'glpi_plugin_erm_risks_items',
              'glpi_plugin_erm_entityconfigs', 'glpi_plugin_erm_appetites', 'glpi_plugin_erm_events',
              'glpi_plugin_erm_events_items', 'glpi_plugin_erm_reviews', 'glpi_plugin_erm_alerts',
              'glpi_plugin_erm_snapshots', 'glpi_plugin_erm_aisystems', 'glpi_plugin_erm_aiassessments',
              'glpi_plugin_erm_concerns'] as $table
    ) {
        $DB->dropTable($table, true);
    }
    ProfileRight::deleteProfileRights(Rights::all());
    foreach (['GlpiPlugin\\Erm\\Risk', 'GlpiPlugin\\Erm\\Category', 'GlpiPlugin\\Erm\\Action', 'GlpiPlugin\\Erm\\Event', 'GlpiPlugin\\Erm\\AiSystem'] as $itemtype) {
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
    // Tables without their own entity: only rows of the risks / events the user can see (API lists, search)
    $byRisk = [GlpiPlugin\Erm\Indicator::class, GlpiPlugin\Erm\Review::class, GlpiPlugin\Erm\Risk_Item::class];
    if (in_array($itemtype, $byRisk, true)) {
        return [[$itemtype::getTable() . '.plugin_erm_risks_id' => Rights::visibleRisksSubQuery()]];
    }
    if ($itemtype === GlpiPlugin\Erm\IndicatorValue::class) {
        return [[GlpiPlugin\Erm\IndicatorValue::getTable() . '.plugin_erm_indicators_id' => new QuerySubQuery([
            'SELECT' => 'id', 'FROM' => GlpiPlugin\Erm\Indicator::getTable(), 'WHERE' => ['plugin_erm_risks_id' => Rights::visibleRisksSubQuery()],
        ])]];
    }
    if ($itemtype === GlpiPlugin\Erm\Event_Item::class) {
        $events = GlpiPlugin\Erm\Event::getTable();
        return [[GlpiPlugin\Erm\Event_Item::getTable() . '.plugin_erm_events_id' => new QuerySubQuery([
            'SELECT' => "$events.id", 'FROM' => $events, 'WHERE' => getEntitiesRestrictCriteria($events, '', '', true),
        ])]];
    }
    if ($itemtype === Action::class && Rights::onlyOwn()) {
        // Actions of own risks, plus the actions assigned to the user
        return [['OR' => [
            Action::getTable() . '.users_id' => (int) Session::getLoginUserID(),
            Action::getTable() . '.plugin_erm_risks_id' => new QuerySubQuery([
                'SELECT' => 'id', 'FROM' => Risk::getTable(), 'WHERE' => [Rights::ownCriteria(Risk::getTable())],
            ]),
        ]]];
    }
    return [];
}

/**
 * A GLPI object linked to risks or loss events was purged: remove its links.
 */
function plugin_erm_item_purge(CommonDBTM $item): void
{
    global $DB;
    $DB->delete(GlpiPlugin\Erm\Risk_Item::getTable(), ['itemtype' => $item::class, 'items_id' => $item->getID()]);
    $DB->delete(GlpiPlugin\Erm\Event_Item::getTable(), ['itemtype' => $item::class, 'items_id' => $item->getID()]);
}

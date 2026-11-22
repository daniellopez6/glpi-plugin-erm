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

namespace GlpiPlugin\Erm;

use CommonTreeDropdown;
use Dropdown;
use Session;

/**
 * Corporate risk category (configurable tree) with risk appetite and tolerance.
 */
class Category extends CommonTreeDropdown
{
    public static string $rightname = Rights::CONFIG;
    public bool $dohistory = true;
    public bool $can_be_translated = false;

    /**
     * The taxonomy and appetite can be read with the configuration right or with read access to the register.
     */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ) || Rights::canReadSome();
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Corporate risk category', 'Corporate risk categories', $nb, 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-sitemap';
    }

    public static function getMenuName()
    {
        return __('Categories and appetite (ERM)', 'erm');
    }

    public function getAdditionalFields()
    {
        return array_merge(parent::getAdditionalFields(), [
           ['name' => 'appetite_level', 'label' => __('Risk appetite (maximum desired residual level)', 'erm'), 'type' => 'erm_level'],
           ['name' => 'tolerance_level', 'label' => __('Risk tolerance (maximum acceptable residual level)', 'erm'), 'type' => 'erm_level'],
           ['name' => 'appetite_statement', 'label' => __('Appetite statement', 'erm'), 'type' => 'textarea'],
        ]);
    }

    public function displaySpecificTypeField($ID, $field = [], array $options = [])
    {
        if (($field['type'] ?? '') === 'erm_level') {
            Dropdown::showFromArray($field['name'], ['' => __('Inherited from parent category', 'erm')] + Scale::levels(), [
              'value' => $this->fields[$field['name']] ?? '',
            ]);
        }
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();
        $tab[] = ['id' => 101, 'table' => $this->getTable(), 'field' => 'appetite_level', 'name' => __('Appetite', 'erm'), 'datatype' => 'specific'];
        $tab[] = ['id' => 102, 'table' => $this->getTable(), 'field' => 'tolerance_level', 'name' => __('Tolerance', 'erm'), 'datatype' => 'specific'];
        $tab[] = ['id' => 103, 'table' => $this->getTable(), 'field' => 'appetite_statement', 'name' => __('Appetite statement', 'erm'), 'datatype' => 'text'];
        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if (in_array($field, ['appetite_level', 'tolerance_level'], true)) {
            return $values[$field] ? Scale::badge($values[$field]) : '-';
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if (in_array($field, ['appetite_level', 'tolerance_level'], true)) {
            return Dropdown::showFromArray($name, ['' => '-'] + Scale::levels(), ['display' => false, 'value' => $values[$field] ?? '']);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Effective appetite and tolerance of a category for an entity: entity overrides (inherited from parent
     * entities) take precedence over the category defaults; missing values are inherited from parent categories.
     *
     * @return array{0:string,1:string}
     */
    public static function effectiveAppetite(int $id, int $entityId = 0): array
    {
        return AppetiteResolver::resolve(
            EntityConfig::categoryChain($id),
            EntityConfig::entityChain($entityId),
            EntityConfig::overrides(),
            EntityConfig::categories()
        );
    }

    /**
     * Initial taxonomy (ISO 31000 / COSO ERM), created in the language of the user running the installation.
     */
    public static function createDefaultTaxonomy(): void
    {
        $tree = [
           [__('Strategic', 'erm'), 'medium', 'high',
               __('Moderate risks are accepted to pursue growth, without compromising solvency.', 'erm'),
               [__('Business model and competition', 'erm'), __('Strategic projects and initiatives', 'erm'), __('Corporate governance', 'erm')]],
           [__('Financial', 'erm'), 'medium', 'high',
               __('Moderate financial risks consistent with the business are accepted; no risk may compromise minimum liquidity or regulatory capital.', 'erm'),
               [__('Credit and counterparty', 'erm'), __('Liquidity and funding', 'erm'), __('Market (interest and exchange rates)', 'erm'), __('Capital and solvency', 'erm')]],
           [__('Operational', 'erm'), 'medium', 'high',
               __('Minor operational failures are tolerated when recovered within the agreed recovery time.', 'erm'),
               [__('Processes', 'erm'), __('People', 'erm'), __('Technology and information security', 'erm'), __('Third parties and suppliers', 'erm'), __('Business continuity', 'erm')]],
           [__('Compliance and legal', 'erm'), 'low', 'medium',
               __('Minimal tolerance: regulatory or legal breaches are corrected as a priority and never accepted deliberately.', 'erm'),
               [__('Regulatory', 'erm'), __('Anti-money laundering (AML/CFT)', 'erm'), __('Consumer protection', 'erm'), __('Litigation and contracts', 'erm')]],
           [__('Fraud', 'erm'), 'low', 'medium',
               __('Zero tolerance for internal fraud; external fraud is kept limited by preventive controls.', 'erm'),
               [__('Internal fraud', 'erm'), __('External fraud', 'erm')]],
           [__('Reputational', 'erm'), 'medium', 'high',
               __('The trust of customers, regulators and investors is protected; events with public exposure are escalated to the Board.', 'erm'),
               []],
           [__('ESG and climate', 'erm'), 'medium', 'high',
               __('Environmental, social and climate risks are managed progressively.', 'erm'),
               [__('Climate (physical and transition)', 'erm'), __('Social', 'erm')]],
        ];
        // Internal keys in the same order as the tree (TAXONOMY), so that any installation language gets them
        $rootKeys = array_values(array_filter(array_keys(self::TAXONOMY), fn($k) => !str_contains($k, '.') && $k !== 'ai'));
        foreach ($tree as $i => [$name, $app, $tol, $stmt, $children]) {
            $rootKey = $rootKeys[$i] ?? '';
            $childKeys = array_values(array_filter(array_keys(self::TAXONOMY), fn($k) => str_starts_with($k, $rootKey . '.')));
            $pid = (new self())->add(['name' => $name, 'plugin_erm_categories_id' => 0, 'entities_id' => 0, 'is_recursive' => 1,
                                     'appetite_level' => $app, 'tolerance_level' => $tol, 'appetite_statement' => $stmt,
                                     'erm_key' => $rootKey]);
            foreach ($children as $j => $child) {
                (new self())->add(['name' => $child, 'plugin_erm_categories_id' => $pid, 'entities_id' => 0, 'is_recursive' => 1,
                                   'erm_key' => $childKeys[$j] ?? '']);
            }
        }
        self::createAiTaxonomy();
        self::assignTaxonomyKeys();
    }

    /**
     * Internal keys of the standard taxonomy with their names in English and Spanish (used to recognise the
     * categories of existing installations and to place the risk library templates).
     *
     * @var array<string, array{0:string, 1:string}>
     */
    public const TAXONOMY = [
        'strategic' => ['Strategic', 'Estratégico'],
        'strategic.business' => ['Business model and competition', 'Modelo de negocio y competencia'],
        'strategic.projects' => ['Strategic projects and initiatives', 'Proyectos e iniciativas estratégicas'],
        'strategic.governance' => ['Corporate governance', 'Gobierno corporativo'],
        'financial' => ['Financial', 'Financiero'],
        'financial.credit' => ['Credit and counterparty', 'Crédito y contraparte'],
        'financial.liquidity' => ['Liquidity and funding', 'Liquidez y fondeo'],
        'financial.market' => ['Market (interest and exchange rates)', 'Mercado (tasa y tipo de cambio)'],
        'financial.capital' => ['Capital and solvency', 'Capital y solvencia'],
        'operational' => ['Operational', 'Operacional'],
        'operational.processes' => ['Processes', 'Procesos'],
        'operational.people' => ['People', 'Personas'],
        'operational.technology' => ['Technology and information security', 'Tecnología y seguridad de la información'],
        'operational.third_parties' => ['Third parties and suppliers', 'Terceros y proveedores'],
        'operational.continuity' => ['Business continuity', 'Continuidad del negocio'],
        'compliance' => ['Compliance and legal', 'Cumplimiento y legal'],
        'compliance.regulatory' => ['Regulatory', 'Regulatorio'],
        'compliance.aml' => ['Anti-money laundering (AML/CFT)', 'Prevención de lavado de activos (PLA/FT)'],
        'compliance.consumer' => ['Consumer protection', 'Protección del consumidor'],
        'compliance.litigation' => ['Litigation and contracts', 'Litigios y contratos'],
        'fraud' => ['Fraud', 'Fraude'],
        'fraud.internal' => ['Internal fraud', 'Fraude interno'],
        'fraud.external' => ['External fraud', 'Fraude externo'],
        'reputational' => ['Reputational', 'Reputacional'],
        'esg' => ['ESG and climate', 'ESG y climático'],
        'esg.climate' => ['Climate (physical and transition)', 'Climático (físico y de transición)'],
        'esg.social' => ['Social', 'Social'],
        'ai' => ['Artificial intelligence', 'Inteligencia artificial'],
        'ai.bias' => ['Bias and discrimination', 'Sesgo y discriminación'],
        'ai.privacy' => ['Privacy and personal data', 'Privacidad y datos personales'],
        'ai.reliability' => ['Reliability, accuracy and hallucinations', 'Fiabilidad, exactitud y alucinaciones'],
        'ai.misuse' => ['Misuse, attacks and prompt injection', 'Uso indebido, ataques e inyección de instrucciones'],
        'ai.transparency' => ['Transparency and accountability', 'Transparencia y rendición de cuentas'],
        'ai.providers' => ['Dependence on AI providers and models', 'Dependencia de proveedores y modelos de IA'],
    ];

    /** Marker used by 2.0.x in the comment of the AI root category (migrated to the erm_key field in 2.1.0). */
    public const AI_MARKER = '[erm:ai]';

    /**
     * Creates the "Artificial intelligence" branch (ISO/IEC 42001, AI Act) once.
     */
    public static function createAiTaxonomy(): void
    {
        if (self::aiRootId() > 0) {
            return;
        }
        $pid = (new self())->add([
            'name' => __('Artificial intelligence', 'erm'), 'plugin_erm_categories_id' => 0, 'entities_id' => 0, 'is_recursive' => 1,
            'appetite_level' => 'low', 'tolerance_level' => 'medium',
            'appetite_statement' => __('AI is adopted to improve services, but no AI system may discriminate, expose personal data, mislead customers or act without the human oversight its impact requires.', 'erm'),
            'comment' => __('Risks of the AI systems of the organization (ISO/IEC 42001, AI Act).', 'erm'),
            'erm_key' => 'ai',
        ]);
        $children = [
            'ai.bias'         => __('Bias and discrimination', 'erm'),
            'ai.privacy'      => __('Privacy and personal data', 'erm'),
            'ai.reliability'  => __('Reliability, accuracy and hallucinations', 'erm'),
            'ai.misuse'       => __('Misuse, attacks and prompt injection', 'erm'),
            'ai.transparency' => __('Transparency and accountability', 'erm'),
            'ai.providers'    => __('Dependence on AI providers and models', 'erm'),
        ];
        foreach ($children as $key => $child) {
            (new self())->add(['name' => $child, 'plugin_erm_categories_id' => $pid, 'entities_id' => 0, 'is_recursive' => 1, 'erm_key' => $key]);
        }
    }

    /** Id of the category with an internal key (0 when missing). */
    public static function idByKey(string $key): int
    {
        global $DB;
        $row = $DB->request(['SELECT' => 'id', 'FROM' => self::getTable(), 'WHERE' => ['erm_key' => $key], 'LIMIT' => 1])->current();
        return (int) ($row['id'] ?? 0);
    }

    /**
     * Gives their internal key to the standard categories of an existing installation, recognised by their English
     * or Spanish name (renamed categories are left without key).
     */
    public static function assignTaxonomyKeys(): int
    {
        global $DB;
        $n = 0;
        foreach (self::TAXONOMY as $key => [$en, $es]) {
            if (self::idByKey($key) > 0) {
                continue;
            }
            $parentKey = str_contains($key, '.') ? substr($key, 0, (int) strpos($key, '.')) : '';
            $parentId = $parentKey === '' ? 0 : self::idByKey($parentKey);
            if ($parentKey !== '' && $parentId === 0) {
                continue;
            }
            $row = $DB->request(['SELECT' => 'id', 'FROM' => self::getTable(), 'WHERE' => [
                'erm_key' => '', 'plugin_erm_categories_id' => $parentId, 'name' => [$en, $es],
            ], 'LIMIT' => 1])->current();
            if ($row) {
                $DB->update(self::getTable(), ['erm_key' => $key], ['id' => $row['id']]);
                $n++;
            }
        }
        return $n;
    }

    /** Id of the "Artificial intelligence" root category (0 when missing). */
    public static function aiRootId(): int
    {
        global $DB;
        $row = $DB->request(['SELECT' => 'id', 'FROM' => self::getTable(), 'WHERE' => ['erm_key' => 'ai'], 'LIMIT' => 1])->current();
        return (int) ($row['id'] ?? 0);
    }
}

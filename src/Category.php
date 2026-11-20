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
        foreach ($tree as [$name, $app, $tol, $stmt, $children]) {
            $pid = (new self())->add(['name' => $name, 'plugin_erm_categories_id' => 0, 'entities_id' => 0, 'is_recursive' => 1,
                                     'appetite_level' => $app, 'tolerance_level' => $tol, 'appetite_statement' => $stmt]);
            foreach ($children as $child) {
                (new self())->add(['name' => $child, 'plugin_erm_categories_id' => $pid, 'entities_id' => 0, 'is_recursive' => 1]);
            }
        }
    }
}

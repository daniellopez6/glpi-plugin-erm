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

use CommonDBChild;
use CommonGLPI;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Treatment plan action of a corporate risk.
 */
class Action extends CommonDBChild
{
    public static string $itemtype = Risk::class;
    public static string $items_id = 'plugin_erm_risks_id';
    public static string $rightname = Rights::TREATMENT;
    public static int $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;
    public bool $dohistory = true;

    public static function canView(): bool
    {
        return Risk::canView();
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Treatment action', 'Treatment plan', $nb, 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-list-check';
    }

    public static function getMenuName()
    {
        return __('Action plans', 'erm');
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/action.php', $full);
    }

    public static function getFormURL($full = true)
    {
        return plugin_erm_url('front/action.form.php', $full);
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        return ['title' => self::getMenuName(), 'page' => self::getSearchURL(false), 'icon' => self::getIcon(),
                'links' => ['search' => self::getSearchURL(false)]];
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(\Log::class, $tabs, $options);
        return $tabs;
    }

    public function rawSearchOptions()
    {
        $t = self::getTable();
        $r = Risk::getTable();
        $tab = [['id' => 'common', 'name' => __('Action plans', 'erm')]];
        $tab[] = ['id' => 1, 'table' => $t, 'field' => 'name', 'name' => __('Action', 'erm'), 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 2, 'table' => $t, 'field' => 'id', 'name' => __('ID'), 'datatype' => 'number', 'massiveaction' => false];
        $tab[] = ['id' => 3, 'table' => $r, 'field' => 'code', 'name' => __('Risk code', 'erm'), 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 4, 'table' => $t, 'field' => 'due_date', 'name' => __('Due date', 'erm'), 'datatype' => 'date'];
        $tab[] = ['id' => 5, 'table' => $t, 'field' => 'status', 'name' => __('Status', 'erm'), 'datatype' => 'specific', 'searchtype' => ['equals', 'notequals']];
        $tab[] = ['id' => 6, 'table' => $r, 'field' => 'name', 'name' => __('Risk', 'erm'), 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 7, 'table' => 'glpi_users', 'field' => 'name', 'name' => __('Responsible', 'erm'), 'datatype' => 'dropdown', 'right' => 'all'];
        $tab[] = ['id' => 8, 'table' => $t, 'field' => 'completion_date', 'name' => __('Done on', 'erm'), 'datatype' => 'date'];
        $tab[] = ['id' => 9, 'table' => $t, 'field' => 'cost', 'name' => __('Estimated cost', 'erm'), 'datatype' => 'decimal'];
        $tab[] = ['id' => 10, 'table' => $t, 'field' => 'comment', 'name' => __('Comments'), 'datatype' => 'text'];
        $tab[] = ['id' => 11, 'table' => $r, 'field' => 'residual_level', 'name' => __('Residual level of the risk', 'erm'), 'datatype' => 'specific',
                  'additionalfields' => ['residual_score'], 'massiveaction' => false];
        $tab[] = ['id' => 12, 'table' => $t, 'field' => 'date_mod', 'name' => __('Last update'), 'datatype' => 'datetime', 'massiveaction' => false];
        $tab[] = ['id' => 80, 'table' => 'glpi_entities', 'field' => 'completename', 'name' => \Entity::getTypeName(1), 'datatype' => 'dropdown',
                  'massiveaction' => false];
        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        return match ($field) {
            'status'         => htmlescape(Scale::actionStatuses()[$values[$field]] ?? $values[$field]),
            'residual_level' => Scale::badge($values[$field], (int) ($values['residual_score'] ?? 0) ?: null),
            default          => parent::getSpecificValueToDisplay($field, $values, $options),
        };
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $map = ['status' => Scale::actionStatuses(), 'residual_level' => Scale::levels()];
        if (isset($map[$field])) {
            return Dropdown::showFromArray($name, $map[$field], ['display' => false, 'value' => $values[$field] ?? '']);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $risk = new Risk();
        $riskId = (int) ($this->fields['plugin_erm_risks_id'] ?? 0);
        $hasRisk = $riskId > 0 && $risk->getFromDB($riskId);
        $today = date('Y-m-d');
        TemplateRenderer::getInstance()->display('@erm/action_form.html.twig', [
           'item'     => $this,
           'params'   => $options,
           'risk'     => $hasRisk ? $risk : null,
           'statuses' => Scale::actionStatuses(),
           'overdue'  => !empty($this->fields['due_date']) && $this->fields['due_date'] < $today
               && !in_array($this->fields['status'] ?? '', ['done', 'cancelled'], true),
        ]);
        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Risk && !$item->isNewItem()) {
            $n = countElementsInTable(self::getTable(), ['plugin_erm_risks_id' => $item->getID()]);
            return self::createTabEntry(__('Treatment plan', 'erm'), $n, null, self::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $DB;
        if (!$item instanceof Risk) {
            return false;
        }
        $rows = [];
        $today = date('Y-m-d');
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_erm_risks_id' => $item->getID()], 'ORDER' => ['due_date', 'id']]) as $r) {
            $r['owner'] = $r['users_id'] ? getUserName($r['users_id']) : '';
            $r['overdue'] = $r['due_date'] && $r['due_date'] < $today && !in_array($r['status'], ['done', 'cancelled'], true);
            $rows[] = $r;
        }
        TemplateRenderer::getInstance()->display('@erm/actions_tab.html.twig', [
           'risk'     => $item,
           'rows'     => $rows,
           'statuses' => Scale::actionStatuses(),
           'can_edit' => Session::haveRight(self::$rightname, UPDATE),
           'can_add'  => Session::haveRight(self::$rightname, CREATE),
           'can_purge' => Session::haveRight(self::$rightname, PURGE),
           'action'   => plugin_erm_url('front/action.form.php'),
        ]);
        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (trim((string) ($input['name'] ?? '')) === '') {
            Session::addMessageAfterRedirect(__('The action must have a description.', 'erm'), false, ERROR);
            return false;
        }
        // Actions always inherit the entity of their risk (never taken from the request)
        unset($input['entities_id'], $input['is_recursive']);
        $risk = new Risk();
        if (!$risk->getFromDB((int) ($input['plugin_erm_risks_id'] ?? 0)) || !$risk->canViewItem()) {
            Session::addMessageAfterRedirect(__('The risk of the action is not valid.', 'erm'), false, ERROR);
            return false;
        }
        $input['entities_id'] = $risk->fields['entities_id'];
        $input['is_recursive'] = $risk->fields['is_recursive'];
        return $this->clean($input);
    }

    public function prepareInputForUpdate($input)
    {
        // An action cannot be moved to another risk or entity
        unset($input['plugin_erm_risks_id'], $input['entities_id'], $input['is_recursive']);
        $input = $this->clean($input);
        if ($input === false) {
            return false;
        }
        if (($input['status'] ?? '') === 'done' && empty($input['completion_date']) && empty($this->fields['completion_date'])) {
            $input['completion_date'] = date('Y-m-d');
        }
        return $input;
    }

    /**
     * Valida fechas, costo y estado.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>|false
     */
    private function clean(array $input): array|false
    {
        $input = Validator::apply($input, ['name' => 'required', 'due_date' => 'date', 'completion_date' => 'date', 'cost' => 'decimal']);
        if ($input === false) {
            return false;
        }
        if (isset($input['status']) && !array_key_exists($input['status'], Scale::actionStatuses())) {
            $input['status'] = 'planned';
        }
        return $input;
    }
}

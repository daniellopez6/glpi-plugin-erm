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
        return $this->clean($input);
    }

    public function prepareInputForUpdate($input)
    {
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

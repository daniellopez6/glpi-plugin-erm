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

use CommonDBTM;
use CommonGLPI;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;

/**
 * Link between a corporate risk and other GLPI or GRC Manager objects.
 */
class Risk_Item extends CommonDBTM
{
    public static string $rightname = Rights::RISK;

    public static function canView(): bool
    {
        return Risk::canView();
    }

    /** Linking or unlinking requires the right to update the risk. */
    public static function canEditRisk(int $riskId): bool
    {
        $risk = new Risk();
        return $risk->getFromDB($riskId) && $risk->canUpdateItem() && Risk::canUpdate();
    }

    public static function getTypeName($nb = 0)
    {
        return __('Related items', 'erm');
    }

    /** Item-level rights follow the corporate risk (also for the REST API). */
    public function canViewItem(): bool
    {
        $risk = new Risk();
        return $risk->getFromDB((int) ($this->fields['plugin_erm_risks_id'] ?? 0)) && $risk->canViewItem();
    }

    public function canCreateItem(): bool
    {
        return self::canEditRisk((int) ($this->input['plugin_erm_risks_id'] ?? 0));
    }

    public function canUpdateItem(): bool
    {
        return self::canEditRisk((int) ($this->fields['plugin_erm_risks_id'] ?? 0));
    }

    public function canDeleteItem(): bool
    {
        return $this->canUpdateItem();
    }

    public function canPurgeItem(): bool
    {
        return $this->canUpdateItem();
    }

    /** Only the comment of a link can change. */
    public function prepareInputForUpdate($input)
    {
        return array_intersect_key($input, array_flip(['id', 'comment']));
    }

    public static function getIcon()
    {
        return 'ti ti-link';
    }

    /** Linkable itemtypes: GRC risks and processes, compliance objects and business assets. */
    public static function getLinkableItemtypes(): array
    {
        $types = [AiSystem::class, 'Supplier', 'Contract', 'Appliance', 'Project', 'Computer', 'Software', 'Group', 'Change'];
        $grc = ['GlpiPlugin\\Grcmanager\\Risk', 'GlpiPlugin\\Grcmanager\\BusinessProcess', 'GlpiPlugin\\Grcmanager\\SupplierRisk',
               'GlpiPlugin\\Grcmanager\\ComplianceObligation', 'GlpiPlugin\\Grcmanager\\Control', 'GlpiPlugin\\Grcmanager\\Objective'];
        foreach ($grc as $t) {
            if (class_exists($t)) {
                array_unshift($types, $t);
            }
        }
        return $types;
    }

    public function prepareInputForAdd($input)
    {
        if (Session::getLoginUserID() !== false && !self::canEditRisk((int) ($input['plugin_erm_risks_id'] ?? 0))) {
            Session::addMessageAfterRedirect(__('You are not allowed to update this risk.', 'erm'), false, ERROR);
            return false;
        }
        if (empty($input['itemtype']) || empty($input['items_id']) || !in_array($input['itemtype'], self::getLinkableItemtypes(), true)) {
            Session::addMessageAfterRedirect(__('Select an item to link.', 'erm'), false, ERROR);
            return false;
        }
        $target = getItemForItemtype($input['itemtype']);
        if (!$target || !$target->getFromDB((int) $input['items_id']) || !$target->canViewItem()) {
            Session::addMessageAfterRedirect(__('The item does not exist or you are not allowed to see it.', 'erm'), false, ERROR);
            return false;
        }
        if (
            countElementsInTable(self::getTable(), ['plugin_erm_risks_id' => (int) $input['plugin_erm_risks_id'],
            'itemtype' => $input['itemtype'], 'items_id' => (int) $input['items_id']]) > 0
        ) {
            Session::addMessageAfterRedirect(__('The item is already linked.', 'erm'), false, WARNING);
            return false;
        }
        $input['date_creation'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function post_addItem()
    {
        $risk = new Risk();
        if ($risk->getFromDB((int) $this->fields['plugin_erm_risks_id'])) {
            \Log::history($risk->getID(), Risk::class, [0, '', sprintf(__('Linked with %s', 'erm'), $this->label())], '', \Log::HISTORY_LOG_SIMPLE_MESSAGE);
        }
    }

    private function label(): string
    {
        $t = $this->fields['itemtype'];
        return $t::getTypeName(1) . ' #' . $this->fields['items_id'];
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof CommonDBTM || $item->isNewItem() || !Risk::canView()) {
            return '';
        }
        if ($item instanceof Risk) {
            $n = countElementsInTable(self::getTable(), ['plugin_erm_risks_id' => $item->getID()]);
            return self::createTabEntry(__('Related items', 'erm'), $n, null, self::getIcon());
        }
        if (in_array($item::class, self::getLinkableItemtypes(), true)) {
            $n = countElementsInTable(self::getTable(), ['itemtype' => $item::class, 'items_id' => $item->getID()]);
            return self::createTabEntry(__('Corporate risks', 'erm'), $n, null, Risk::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $DB;
        if (!$item instanceof CommonDBTM) {
            return false;
        }
        $action = plugin_erm_url('front/risk_item.form.php');

        if ($item instanceof Risk) {
            $canEdit = self::canEditRisk($item->getID());
            $rows = [];
            foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_erm_risks_id' => $item->getID()], 'ORDER' => ['itemtype', 'items_id']]) as $r) {
                $obj = getItemForItemtype($r['itemtype']);
                if (!$obj || !$obj->getFromDB($r['items_id'])) {
                    continue;
                }
                $rows[] = ['id' => $r['id'], 'type' => $obj::getTypeName(1), 'link' => $obj->getLink(), 'comment' => $r['comment'],
                       'icon' => $obj::getIcon()];
            }
            TemplateRenderer::getInstance()->display('@erm/items_tab.html.twig', ['rows' => $rows, 'can_edit' => $canEdit, 'action' => $action]);
            if ($canEdit) {
                echo "<div class='card mt-3'><div class='card-body'><form method='post' action='" . htmlescape($action) . "' class='row g-2 align-items-end'>";
                echo Html::hidden('plugin_erm_risks_id', ['value' => $item->getID()]);
                echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
                echo "<div class='col-md-7'><label class='form-label'>" . htmlescape(__('Link with', 'erm')) . "</label>";
                Dropdown::showSelectItemFromItemtypes(['itemtypes' => self::getLinkableItemtypes(), 'entity_restrict' => $item->fields['entities_id']]);
                echo "</div><div class='col-md-3'><label class='form-label'>" . htmlescape(__('Comment', 'erm')) . "</label><input type='text' name='comment' class='form-control'></div>";
                echo "<div class='col-md-2'><button type='submit' name='add' class='btn btn-primary w-100'><i class='ti ti-link'></i> " . htmlescape(__('Link', 'erm')) . "</button></div>";
                echo "</form></div></div>";
            }
            return true;
        }

       // Reverse tab: corporate risks linked to this object (only those visible to the user)
        $canEdit = Risk::canUpdate();
        $rows = [];
        foreach (
            $DB->request([
            'SELECT' => ['l.id AS link_id', 'l.comment', 'r.*'], 'FROM' => self::getTable() . ' AS l',
            'INNER JOIN' => [Risk::getTable() . ' AS r' => ['ON' => ['l' => 'plugin_erm_risks_id', 'r' => 'id']]],
            'WHERE' => ['l.itemtype' => $item::class, 'l.items_id' => $item->getID(), 'r.is_deleted' => 0],
            ]) as $r
        ) {
            $risk = new Risk();
            if (!$risk->getFromDB($r['id']) || !$risk->canViewItem()) {
                continue;
            }
            $rows[] = ['id' => $r['link_id'], 'type' => $r['code'], 'link' => $risk->getLink(), 'comment' => $r['comment'],
                     'level' => Scale::badge($r['residual_level'] ?: $r['inherent_level'], (int) ($r['residual_score'] ?: $r['inherent_score']) ?: null),
                     'icon' => Risk::getIcon()];
        }
        TemplateRenderer::getInstance()->display('@erm/items_tab.html.twig', ['rows' => $rows, 'can_edit' => $canEdit, 'action' => $action, 'reverse' => true]);
        if ($canEdit) {
            echo "<div class='card mt-3'><div class='card-body'><form method='post' action='" . htmlescape($action) . "' class='row g-2 align-items-end'>";
            echo Html::hidden('itemtype', ['value' => $item::class]);
            echo Html::hidden('items_id', ['value' => $item->getID()]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<div class='col-md-6'><label class='form-label'>" . htmlescape(__('Corporate risk', 'erm')) . "</label>";
            Risk::dropdown(['name' => 'plugin_erm_risks_id', 'entity' => $item->fields['entities_id'] ?? -1,
                          'condition' => Rights::onlyOwn() ? [Rights::ownCriteria()] : []]);
            echo "</div><div class='col-md-4'><label class='form-label'>" . htmlescape(__('Comment', 'erm')) . "</label><input type='text' name='comment' class='form-control'></div>";
            echo "<div class='col-md-2'><button type='submit' name='add' class='btn btn-primary w-100'><i class='ti ti-link'></i> " . htmlescape(__('Link', 'erm')) . "</button></div>";
            echo "</form></div></div>";
        }
        return true;
    }
}

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
use CommonITILObject;
use Document_Item;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Log;
use Session;

/**
 * Loss event (incident with economic effect, near miss or unexpected gain) linked to corporate risks and to
 * GLPI tickets, problems or changes. The event history backs the probability and impact of the risks.
 */
class Event extends CommonDBTM
{
    public static string $rightname = Rights::EVENT;
    public bool $dohistory = true;
    protected bool $usenotepad = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Loss event', 'Loss events', $nb, 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-flame';
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/event.php', $full);
    }

    public static function getFormURL($full = true)
    {
        return plugin_erm_url('front/event.form.php', $full);
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        $links = ['search' => self::getSearchURL(false)];
        if (self::canCreate()) {
            $links['add'] = self::getFormURL(false);
        }
        return ['title' => self::getTypeName(2), 'page' => self::getSearchURL(false), 'icon' => self::getIcon(), 'links' => $links];
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(Event_Item::class, $tabs, $options);
        $this->addStandardTab(Document_Item::class, $tabs, $options);
        $this->addStandardTab('Notepad', $tabs, $options);
        $this->addStandardTab(Log::class, $tabs, $options);
        return $tabs;
    }

    // ------------------------------------------------------------------ input
    public function prepareInputForAdd($input)
    {
        $input = $this->clean($input, true);
        if ($input === false) {
            return false;
        }
        if (empty($input['users_id'])) {
            $input['users_id'] = (int) Session::getLoginUserID();
        }
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (!Rights::entityChangeAllowed($input, $this->fields)) {
            return false;
        }
        return $this->clean($input, false);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>|false
     */
    private function clean(array $input, bool $isNew): array|false
    {
        $input = Validator::apply($input, [
            'name' => 'required', 'occurrence_date' => 'date', 'detection_date' => 'date',
            'gross_loss' => 'decimal', 'recoveries' => 'decimal',
        ]);
        if ($input === false) {
            return false;
        }
        if ($isNew && ($input['occurrence_date'] ?? 'NULL') === 'NULL') {
            Session::addMessageAfterRedirect(__('The occurrence date is required.', 'erm'), false, ERROR);
            return false;
        }
        if (isset($input['event_type']) && !array_key_exists($input['event_type'], Scale::eventTypes())) {
            $input['event_type'] = 'loss';
        }
        if (isset($input['status']) && !array_key_exists($input['status'], Scale::eventStatuses())) {
            $input['status'] = 'open';
        }
        $occ = $input['occurrence_date'] ?? ($this->fields['occurrence_date'] ?? null);
        $det = $input['detection_date'] ?? ($this->fields['detection_date'] ?? null);
        if ($occ && $det && $occ !== 'NULL' && $det !== 'NULL' && $det < $occ) {
            Session::addMessageAfterRedirect(__('The detection date cannot be earlier than the occurrence date.', 'erm'), false, ERROR);
            return false;
        }
        // Net loss = gross loss - recoveries
        if (array_key_exists('gross_loss', $input) || array_key_exists('recoveries', $input)) {
            $gross = $input['gross_loss'] ?? ($this->fields['gross_loss'] ?? null);
            $rec = $input['recoveries'] ?? ($this->fields['recoveries'] ?? null);
            $gross = $gross === 'NULL' ? null : $gross;
            $rec = $rec === 'NULL' ? null : $rec;
            $input['net_loss'] = $gross === null ? 'NULL' : (string) ((float) $gross - (float) ($rec ?? 0));
        }
        return $input;
    }

    public function post_addItem()
    {
        // Created from a risk, ticket, problem or change: link it
        if (!empty($this->input['_link_itemtype']) && !empty($this->input['_link_items_id'])) {
            Event_Item::$linkingOnEventCreation = true;
            (new Event_Item())->add([
                'plugin_erm_events_id' => $this->getID(),
                'itemtype'             => $this->input['_link_itemtype'],
                'items_id'             => (int) $this->input['_link_items_id'],
            ]);
            Event_Item::$linkingOnEventCreation = false;
        }
        parent::post_addItem();
    }

    public function cleanDBonPurge()
    {
        global $DB;
        // Event_Item is not a GLPI relation (CommonDBConnexity): clean it explicitly
        $DB->delete(Event_Item::getTable(), ['plugin_erm_events_id' => $this->getID()]);
    }

    // ------------------------------------------------------------------ search
    public function rawSearchOptions()
    {
        $t = self::getTable();
        $tab = [['id' => 'common', 'name' => self::getTypeName(2)]];
        $tab[] = ['id' => 1, 'table' => $t, 'field' => 'name', 'name' => __('Event', 'erm'), 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 2, 'table' => $t, 'field' => 'id', 'name' => __('ID'), 'datatype' => 'number', 'massiveaction' => false];
        $tab[] = ['id' => 3, 'table' => $t, 'field' => 'occurrence_date', 'name' => __('Occurrence date', 'erm'), 'datatype' => 'date'];
        $tab[] = ['id' => 4, 'table' => $t, 'field' => 'detection_date', 'name' => __('Detection date', 'erm'), 'datatype' => 'date'];
        $tab[] = ['id' => 5, 'table' => $t, 'field' => 'event_type', 'name' => __('Event type', 'erm'), 'datatype' => 'specific', 'searchtype' => ['equals', 'notequals']];
        $tab[] = ['id' => 6, 'table' => $t, 'field' => 'status', 'name' => __('Status', 'erm'), 'datatype' => 'specific', 'searchtype' => ['equals', 'notequals']];
        $tab[] = ['id' => 7, 'table' => $t, 'field' => 'gross_loss', 'name' => __('Gross loss', 'erm'), 'datatype' => 'decimal'];
        $tab[] = ['id' => 8, 'table' => $t, 'field' => 'recoveries', 'name' => __('Recoveries', 'erm'), 'datatype' => 'decimal'];
        $tab[] = ['id' => 9, 'table' => $t, 'field' => 'net_loss', 'name' => __('Net loss', 'erm'), 'datatype' => 'decimal', 'massiveaction' => false];
        $tab[] = ['id' => 10, 'table' => Category::getTable(), 'field' => 'completename', 'name' => __('Category', 'erm'), 'datatype' => 'dropdown'];
        $tab[] = ['id' => 11, 'table' => 'glpi_users', 'field' => 'name', 'name' => __('Reported by', 'erm'), 'datatype' => 'dropdown', 'right' => 'all'];
        $tab[] = ['id' => 12, 'table' => $t, 'field' => 'root_cause', 'name' => __('Root cause', 'erm'), 'datatype' => 'text'];
        $tab[] = ['id' => 13, 'table' => $t, 'field' => 'description', 'name' => __('Description', 'erm'), 'datatype' => 'text'];
        $tab[] = ['id' => 14, 'table' => $t, 'field' => 'date_mod', 'name' => __('Last update'), 'datatype' => 'datetime', 'massiveaction' => false];
        $tab[] = ['id' => 80, 'table' => 'glpi_entities', 'field' => 'completename', 'name' => \Entity::getTypeName(1), 'datatype' => 'dropdown'];
        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        return match ($field) {
            'event_type' => htmlescape(Scale::eventTypes()[$values[$field]] ?? $values[$field]),
            'status'     => htmlescape(Scale::eventStatuses()[$values[$field]] ?? $values[$field]),
            default      => parent::getSpecificValueToDisplay($field, $values, $options),
        };
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $map = ['event_type' => Scale::eventTypes(), 'status' => Scale::eventStatuses()];
        if (isset($map[$field])) {
            return Dropdown::showFromArray($name, $map[$field], ['display' => false, 'value' => $values[$field] ?? '']);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    // ------------------------------------------------------------------ form
    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $link = null;
        if ($this->isNewItem()) {
            $link = $this->prefillFrom((string) ($options['_link_itemtype'] ?? ''), (int) ($options['_link_items_id'] ?? 0));
        }
        TemplateRenderer::getInstance()->display('@erm/event_form.html.twig', [
            'item'     => $this,
            'params'   => $options,
            'types'    => Scale::eventTypes(),
            'statuses' => Scale::eventStatuses(),
            'link'     => $link,
        ]);
        return true;
    }

    /**
     * Prefills a new event from the object it is created from (risk, ticket, problem or change).
     *
     * @return array{itemtype:string, items_id:int, label:string}|null
     */
    private function prefillFrom(string $itemtype, int $itemsId): ?array
    {
        if (!in_array($itemtype, Event_Item::getLinkableItemtypes(), true) || $itemsId <= 0) {
            return null;
        }
        $source = getItemForItemtype($itemtype);
        if (!$source || !$source->getFromDB($itemsId) || !$source->canViewItem()) {
            return null;
        }
        $this->fields['entities_id'] = $source->fields['entities_id'];
        if ($source instanceof Risk) {
            $this->fields['plugin_erm_categories_id'] = $source->fields['plugin_erm_categories_id'];
            $label = $source->fields['code'] . ' ' . $source->fields['name'];
        } else {
            $this->fields['name'] = $source->fields['name'];
            if ($source instanceof CommonITILObject) {
                $this->fields['description'] = trim(html_entity_decode(strip_tags((string) $source->fields['content']), ENT_QUOTES | ENT_HTML5));
                $this->fields['occurrence_date'] = substr((string) $source->fields['date'], 0, 10);
                $this->fields['detection_date'] = substr((string) $source->fields['date'], 0, 10);
            }
            $label = $source::getTypeName(1) . ' #' . $itemsId . ' - ' . $source->fields['name'];
        }
        return ['itemtype' => $itemtype, 'items_id' => $itemsId, 'label' => $label];
    }

    // ------------------------------------------------------------------ statistics
    /**
     * Loss events of the given risks (not deleted), indexed by risk.
     *
     * @param list<int> $riskIds
     * @return array<int, list<array<string,mixed>>>
     */
    public static function eventsByRisk(array $riskIds): array
    {
        global $DB;
        $out = [];
        if (!$riskIds) {
            return $out;
        }
        $iter = $DB->request([
            'SELECT'     => ['l.items_id AS risk_id', 'e.id', 'e.name', 'e.event_type', 'e.status', 'e.occurrence_date', 'e.net_loss', 'e.gross_loss'],
            'FROM'       => Event_Item::getTable() . ' AS l',
            'INNER JOIN' => [self::getTable() . ' AS e' => ['ON' => ['l' => 'plugin_erm_events_id', 'e' => 'id']]],
            // Only events of the entities visible to the user count for the statistics
            'WHERE'      => ['l.itemtype' => Risk::class, 'l.items_id' => $riskIds, 'e.is_deleted' => 0]
                + getEntitiesRestrictCriteria('e', '', '', true),
            'ORDER'      => ['e.occurrence_date DESC'],
        ]);
        foreach ($iter as $row) {
            $out[(int) $row['risk_id']][] = $row;
        }
        return $out;
    }

    /**
     * Loss statistics and comparison with the assessment, for each given risk.
     *
     * @param list<array<string,mixed>> $risks rows of glpi_plugin_erm_risks
     * @return array<int, array<string,mixed>>
     */
    public static function historyOf(array $risks): array
    {
        $events = self::eventsByRisk(array_map('intval', array_column($risks, 'id')));
        $today = date('Y-m-d');
        $out = [];
        foreach ($risks as $r) {
            $s = LossStats::summarize($events[(int) $r['id']] ?? [], $today, EntityConfig::impactThresholds((int) $r['entities_id']));
            $prob = (int) ($r['residual_probability'] ?: $r['inherent_probability']);
            $imp = (int) ($r['residual_impact'] ?: $r['inherent_impact']);
            $s['assessed_probability'] = $prob;
            $s['assessed_impact'] = $imp;
            $s['prob_check'] = LossStats::compare($prob, $s['prob_level']);
            $s['impact_check'] = LossStats::compare($imp, $s['impact_level']);
            $s['underestimated'] = $s['prob_check'] === 'underestimated' || $s['impact_check'] === 'underestimated';
            $out[(int) $r['id']] = $s;
        }
        return $out;
    }

    /** Net losses of the visible entities since the given date. */
    public static function netLossSince(string $since): float
    {
        global $DB;
        $row = $DB->request([
            'SELECT' => [new \Glpi\DBAL\QueryExpression('SUM(' . $DB->quoteName('net_loss') . ') AS total')],
            'FROM'   => self::getTable(),
            'WHERE'  => ['is_deleted' => 0, 'event_type' => 'loss', 'occurrence_date' => ['>=', $since]]
                + getEntitiesRestrictCriteria(self::getTable(), '', '', true),
        ])->current();
        return (float) ($row['total'] ?? 0);
    }
}

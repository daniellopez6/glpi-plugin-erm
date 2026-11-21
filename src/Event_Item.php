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

use Change;
use CommonDBTM;
use CommonGLPI;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Problem;
use Session;
use Ticket;

/**
 * Link between a loss event and corporate risks, tickets, problems or changes.
 */
class Event_Item extends CommonDBTM
{
    public static string $rightname = Rights::EVENT;

    public static function getTypeName($nb = 0)
    {
        return __('Linked risks and items', 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-link';
    }

    /** @return list<string> */
    public static function getLinkableItemtypes(): array
    {
        return [Risk::class, Ticket::class, Problem::class, Change::class];
    }

    /** Linking or unlinking requires the right to update the event. */
    public static function canEditEvent(int $eventId): bool
    {
        $event = new Event();
        return $event->getFromDB($eventId) && Event::canUpdate() && $event->canUpdateItem();
    }

    public function prepareInputForAdd($input)
    {
        $itemtype = (string) ($input['itemtype'] ?? '');
        $itemsId = (int) ($input['items_id'] ?? 0);
        $eventId = (int) ($input['plugin_erm_events_id'] ?? 0);
        $event = new Event();
        if (!in_array($itemtype, self::getLinkableItemtypes(), true) || $itemsId <= 0 || !$event->getFromDB($eventId)) {
            Session::addMessageAfterRedirect(__('Select an item to link.', 'erm'), false, ERROR);
            return false;
        }
        $target = getItemForItemtype($itemtype);
        if (!$target || !$target->getFromDB($itemsId) || !$target->canViewItem()) {
            Session::addMessageAfterRedirect(__('The item does not exist or you are not allowed to see it.', 'erm'), false, ERROR);
            return false;
        }
        if (countElementsInTable(self::getTable(), ['plugin_erm_events_id' => $eventId, 'itemtype' => $itemtype, 'items_id' => $itemsId]) > 0) {
            Session::addMessageAfterRedirect(__('The item is already linked.', 'erm'), false, WARNING);
            return false;
        }
        return ['plugin_erm_events_id' => $eventId, 'itemtype' => $itemtype, 'items_id' => $itemsId,
                'comment' => mb_substr(trim((string) ($input['comment'] ?? '')), 0, 255),
                'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s')];
    }

    public function post_addItem()
    {
        $target = getItemForItemtype($this->fields['itemtype']);
        $label = $target && $target->getFromDB($this->fields['items_id']) ? $target->getNameID() : '';
        \Log::history((int) $this->fields['plugin_erm_events_id'], Event::class, [0, '', sprintf(__('Linked with %s', 'erm'), $label)], '', \Log::HISTORY_LOG_SIMPLE_MESSAGE);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof CommonDBTM || $item->isNewItem() || !Event::canView()) {
            return '';
        }
        if ($item instanceof Event) {
            $n = countElementsInTable(self::getTable(), ['plugin_erm_events_id' => $item->getID()]);
            return self::createTabEntry(self::getTypeName(), $n, null, self::getIcon());
        }
        if (in_array($item::class, self::getLinkableItemtypes(), true)) {
            $n = countElementsInTable(self::getTable(), ['itemtype' => $item::class, 'items_id' => $item->getID()]);
            return self::createTabEntry(Event::getTypeName(2), $n, null, Event::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof CommonDBTM) {
            return false;
        }
        if ($item instanceof Event) {
            self::showForEvent($item);
        } else {
            self::showForItem($item);
        }
        return true;
    }

    /** Tab on the event: linked risks, tickets, problems and changes. */
    private static function showForEvent(Event $event): void
    {
        global $DB;
        $rows = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_erm_events_id' => $event->getID()], 'ORDER' => ['itemtype', 'items_id']]) as $r) {
            $obj = getItemForItemtype($r['itemtype']);
            if (!$obj || !$obj->getFromDB($r['items_id']) || !$obj->canViewItem()) {
                continue;
            }
            $rows[] = ['id' => $r['id'], 'type' => $obj::getTypeName(1), 'icon' => $obj::getIcon(), 'link' => $obj->getLink(),
                       'comment' => $r['comment'],
                       'level' => $obj instanceof Risk ? Scale::badge(DrillFilter::level($obj->fields), (int) ($obj->fields['residual_score'] ?: $obj->fields['inherent_score']) ?: null) : ''];
        }
        $canEdit = self::canEditEvent($event->getID());
        $selector = '';
        if ($canEdit) {
            $selector = Dropdown::showSelectItemFromItemtypes(['itemtypes' => self::getLinkableItemtypes(), 'entity_restrict' => $event->fields['entities_id'], 'display' => false]);
        }
        TemplateRenderer::getInstance()->display('@erm/event_items_tab.html.twig', [
            'rows' => $rows, 'can_edit' => $canEdit, 'selector' => $selector, 'event_id' => $event->getID(),
            'action' => plugin_erm_url('front/event_item.form.php'),
        ]);
    }

    /** Tab on a risk, ticket, problem or change: its loss events (and, for a risk, what history says). */
    private static function showForItem(CommonDBTM $item): void
    {
        global $DB;
        $rows = [];
        $iter = $DB->request([
            'SELECT'     => ['l.id AS link_id', 'e.*'],
            'FROM'       => self::getTable() . ' AS l',
            'INNER JOIN' => [Event::getTable() . ' AS e' => ['ON' => ['l' => 'plugin_erm_events_id', 'e' => 'id']]],
            'WHERE'      => ['l.itemtype' => $item::class, 'l.items_id' => $item->getID(), 'e.is_deleted' => 0],
            'ORDER'      => ['e.occurrence_date DESC'],
        ]);
        foreach ($iter as $e) {
            $event = new Event();
            if (!$event->getFromDB($e['id']) || !$event->canViewItem()) {
                continue;
            }
            $e['type_label'] = Scale::eventTypes()[$e['event_type']] ?? $e['event_type'];
            $e['status_label'] = Scale::eventStatuses()[$e['status']] ?? $e['status'];
            $e['can_unlink'] = self::canEditEvent((int) $e['id']);
            $rows[] = $e;
        }
        $history = null;
        if ($item instanceof Risk) {
            $history = Event::historyOf([$item->fields])[$item->getID()];
            $history['assessed_probability_label'] = Scale::probabilities()[$history['assessed_probability']] ?? '-';
            $history['assessed_impact_label'] = Scale::impacts()[$history['assessed_impact']] ?? '-';
            $history['prob_label'] = $history['prob_level'] ? Scale::probabilities()[$history['prob_level']] : '';
            $history['impact_label'] = $history['impact_level'] ? Scale::impacts()[$history['impact_level']] : '';
            $history['probability_pct'] = round($history['probability'] * 100);
            $history['has_thresholds'] = (bool) array_filter(EntityConfig::impactThresholds((int) $item->fields['entities_id']), fn($v) => $v !== null);
        }
        $canLink = Event::canUpdate();
        $dropdown = '';
        if ($canLink) {
            $dropdown = Event::dropdown(['name' => 'plugin_erm_events_id', 'entity' => $item->fields['entities_id'] ?? -1,
                                         'display' => false, 'used' => array_map('intval', array_column($rows, 'id'))]);
        }
        TemplateRenderer::getInstance()->display('@erm/events_tab.html.twig', [
            'rows'      => $rows,
            'history'   => $history,
            'window'    => LossStats::WINDOW_YEARS,
            'can_link'  => $canLink,
            'can_create' => Event::canCreate(),
            'dropdown'  => $dropdown,
            'itemtype'  => $item::class,
            'items_id'  => $item->getID(),
            'action'    => plugin_erm_url('front/event_item.form.php'),
            'new_url'   => Event::getFormURL() . '?' . http_build_query(['_link_itemtype' => $item::class, '_link_items_id' => $item->getID()]),
            'event_url' => Event::getFormURL(),
        ]);
    }
}

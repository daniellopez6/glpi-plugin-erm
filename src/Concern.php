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
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Risk, incident or near miss reported by any user (also from the simplified interface). The risk team reviews it and
 * converts it into a loss event or a corporate risk, or rejects it with a comment; the reporter follows its status.
 */
class Concern extends CommonDBTM
{
    public static string $rightname = Rights::EVENT;

    public static function getTypeName($nb = 0)
    {
        return _n('Risk report', 'Risk reports', $nb, 'erm');
    }

    public static function getMenuName()
    {
        return __('Reports received', 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-speakerphone';
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/concern.php', $full);
    }

    /** Reviewers: can turn reports into loss events or corporate risks. */
    public static function canReview(): bool
    {
        return Session::haveRight(Rights::EVENT, CREATE) || Session::haveRight(Rights::RISK, CREATE);
    }

    public static function canView(): bool
    {
        return self::canReview();
    }

    public static function getMenuContent()
    {
        if (!self::canReview()) {
            return false;
        }
        return ['title' => self::getMenuName(), 'page' => self::getSearchURL(false), 'icon' => self::getIcon()];
    }

    /** Reports are created and reviewed only through their workflow (never with a generic add or update, e.g. the API). */
    public function prepareInputForAdd($input)
    {
        return false;
    }

    public function prepareInputForUpdate($input)
    {
        return false;
    }

    public static function reportTypes(): array
    {
        return [
            'incident'  => __('Something happened that caused a loss or damage', 'erm'),
            'near_miss' => __('Something almost happened (near miss)', 'erm'),
            'risk'      => __('Something could happen (a risk I see)', 'erm'),
        ];
    }

    public static function statuses(): array
    {
        return ['new' => __('Received', 'erm'), 'converted' => __('Taken into account', 'erm'), 'rejected' => __('Closed without action', 'erm')];
    }

    /** Pending reports in the visible entities. */
    public static function countPending(): int
    {
        return countElementsInTable(self::getTable(), ['status' => 'new'] + getEntitiesRestrictCriteria(self::getTable(), '', '', false));
    }

    /**
     * Records a report of the current user in the active entity.
     *
     * @param array<string,mixed> $post
     */
    public static function submit(array $post): bool
    {
        $name = trim((string) ($post['name'] ?? ''));
        $description = trim((string) ($post['description'] ?? ''));
        if ($name === '' || $description === '') {
            Session::addMessageAfterRedirect(__('Describe briefly what you want to report.', 'erm'), false, ERROR);
            return false;
        }
        $input = Validator::apply(
            ['occurrence_date' => $post['occurrence_date'] ?? '', 'estimated_loss' => $post['estimated_loss'] ?? ''],
            ['occurrence_date' => 'date', 'estimated_loss' => 'decimal']
        );
        if ($input === false) {
            return false;
        }
        $type = array_key_exists((string) ($post['report_type'] ?? ''), self::reportTypes()) ? (string) $post['report_type'] : 'risk';
        global $DB;
        return (bool) $DB->insert(self::getTable(), [
            'entities_id'     => (int) ($_SESSION['glpiactive_entity'] ?? 0),
            'users_id'        => (int) Session::getLoginUserID(),
            'report_type'     => $type,
            'name'            => mb_substr($name, 0, 255),
            'description'     => $description,
            'area'            => mb_substr(trim((string) ($post['area'] ?? '')), 0, 255),
            'occurrence_date' => $input['occurrence_date'] === 'NULL' ? null : $input['occurrence_date'],
            'estimated_loss'  => $input['estimated_loss'] === 'NULL' ? null : $input['estimated_loss'],
            'status'          => 'new',
            'date_creation'   => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    /** Loads a pending report the reviewer can see. */
    private static function pending(int $id): ?self
    {
        $c = new self();
        if (!$c->getFromDB($id) || $c->fields['status'] !== 'new' || !Session::haveAccessToEntity((int) $c->fields['entities_id'])) {
            return null;
        }
        return $c;
    }

    private function close(string $status, string $comment, string $itemtype = '', int $itemsId = 0): void
    {
        global $DB;
        $DB->update(self::getTable(), [
            'status' => $status, 'users_id_reviewer' => (int) Session::getLoginUserID(),
            'date_review' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'), 'review_comment' => $comment,
            'result_itemtype' => $itemtype, 'result_items_id' => $itemsId,
        ], ['id' => $this->getID()]);
    }

    /** Converts a report into a loss event (incident or near miss). Returns the event URL or ''. */
    public static function toEvent(int $id): string
    {
        $c = self::pending($id);
        if ($c === null || !Event::canCreate()) {
            return '';
        }
        $event = new Event();
        $data = ['name' => $c->fields['name'], 'description' => $c->fields['description'], 'entities_id' => $c->fields['entities_id'],
                 'event_type' => $c->fields['report_type'] === 'incident' ? 'loss' : 'near_miss', 'status' => 'analysis',
                 'occurrence_date' => $c->fields['occurrence_date'] ?: substr((string) $c->fields['date_creation'], 0, 10),
                 'users_id' => $c->fields['users_id']];
        if ($c->fields['estimated_loss'] !== null) {
            $data['gross_loss'] = $c->fields['estimated_loss'];
        }
        if (!$event->can(-1, CREATE, $data)) {
            return '';
        }
        $eid = $event->add($data);
        if (!$eid) {
            return '';
        }
        $c->close('converted', __('Recorded as a loss event.', 'erm'), Event::class, (int) $eid);
        return Event::getFormURLWithID((int) $eid);
    }

    /** Converts a report into a corporate risk (to be assessed). Returns the risk URL or ''. */
    public static function toRisk(int $id): string
    {
        $c = self::pending($id);
        if ($c === null || !Risk::canCreate()) {
            return '';
        }
        $data = ['name' => $c->fields['name'], 'entities_id' => $c->fields['entities_id'], 'status' => 'identified',
                 'description' => $c->fields['description'] . ($c->fields['area'] ? "\n\n" . sprintf(__('Area: %s', 'erm'), $c->fields['area']) : '')
                     . "\n\n" . sprintf(__('Reported by %s', 'erm'), getUserName((int) $c->fields['users_id']))];
        $risk = new Risk();
        if (!$risk->can(-1, CREATE, $data)) {
            return '';
        }
        $rid = $risk->add($data);
        if (!$rid) {
            return '';
        }
        $c->close('converted', __('Registered as a corporate risk to be assessed.', 'erm'), Risk::class, (int) $rid);
        return Risk::getFormURLWithID((int) $rid);
    }

    public static function reject(int $id, string $comment): bool
    {
        $c = self::pending($id);
        if ($c === null || !self::canReview()) {
            return false;
        }
        if (trim($comment) === '') {
            Session::addMessageAfterRedirect(__('Explain to the reporter why the report is closed.', 'erm'), false, ERROR);
            return false;
        }
        $c->close('rejected', $comment);
        return true;
    }

    /** Page: report form, my reports and (for reviewers) the inbox. */
    public static function show(): void
    {
        global $DB;
        $uid = (int) Session::getLoginUserID();
        $labels = self::statuses();
        $mine = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['users_id' => $uid], 'ORDER' => 'date_creation DESC', 'LIMIT' => 20]) as $r) {
            $r['status_label'] = $labels[$r['status']] ?? $r['status'];
            $mine[] = $r;
        }
        $inbox = [];
        if (self::canReview()) {
            $iter = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['status' => 'new'] + getEntitiesRestrictCriteria(self::getTable(), '', '', false),
                                  'ORDER' => 'date_creation']);
            foreach ($iter as $r) {
                $r['reporter'] = getUserName((int) $r['users_id']);
                $r['entity'] = \Dropdown::getDropdownName('glpi_entities', (int) $r['entities_id']);
                $inbox[] = $r;
            }
        }
        TemplateRenderer::getInstance()->display('@erm/concern.html.twig', [
            'types'        => self::reportTypes(),
            'mine'         => $mine,
            'inbox'        => $inbox,
            'can_review'   => self::canReview(),
            'can_event'    => Event::canCreate(),
            'can_risk'     => Risk::canCreate(),
            'action'       => self::getSearchURL(),
            'today'        => date('Y-m-d'),
        ]);
    }
}

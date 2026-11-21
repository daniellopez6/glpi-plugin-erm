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

use Notification;
use NotificationTarget;
use User;

/**
 * Recipients and tags of the ERM e-mail alerts (daily digest per risk).
 */
class NotificationTargetRisk extends NotificationTarget
{
    public const EVENT = 'erm_alert';
    public const EVENT_OWNER = 'erm_owner_assigned';
    public const EVENT_ACTION = 'erm_action_assigned';
    public const EVENT_ACTION_DONE = 'erm_action_done';

    public const RISK_OWNER = 4101;
    public const RISK_GROUP = 4102;
    public const ACTION_RESPONSIBLES = 4103;
    public const KRI_RESPONSIBLES = 4104;

    public function getEvents()
    {
        return [
            self::EVENT        => __('Corporate risk alerts (daily digest)', 'erm'),
            self::EVENT_OWNER  => __('A corporate risk has been assigned to you', 'erm'),
            self::EVENT_ACTION => __('A treatment action has been assigned to you', 'erm'),
            self::EVENT_ACTION_DONE => __('A treatment action of your risk has been completed', 'erm'),
        ];
    }

    public static function alertLabels(): array
    {
        return [
            'owner_assigned'  => __('You are the owner of this risk', 'erm'),
            'action_assigned' => __('Treatment action assigned to you', 'erm'),
            'action_done'     => __('Treatment action completed', 'erm'),
            'plan_completed'  => __('Treatment plan completed', 'erm'),
            'action_overdue'  => __('Overdue treatment action', 'erm'),
            'action_due'      => __('Treatment action due soon', 'erm'),
            'review_overdue'  => __('Risk review overdue', 'erm'),
            'review_due'      => __('Risk review due soon', 'erm'),
            'above_appetite'  => __('Residual risk above appetite', 'erm'),
            'kri_alert'       => __('KRI in alert', 'erm'),
            'kri_measure'     => __('KRI measurement pending', 'erm'),
            'above_tolerance' => __('Residual risk above tolerance', 'erm'),
            'history'         => __('Assessment below the loss history', 'erm'),
            'ineffective'     => __('Treatment completed without reaching the target', 'erm'),
            'acceptance_expiring' => __('Risk acceptance expires soon', 'erm'),
            'acceptance_expired'  => __('Risk acceptance expired', 'erm'),
            'control_failed'  => __('Linked ISMS control failed its last test', 'erm'),
        ];
    }

    public function addAdditionalTargets($event = '')
    {
        $this->addTarget(self::RISK_OWNER, __('Risk owner', 'erm'));
        $this->addTarget(self::RISK_GROUP, __('Responsible group of the risk', 'erm'));
        $this->addTarget(self::ACTION_RESPONSIBLES, __('Responsibles of the alerted actions', 'erm'));
        $this->addTarget(self::KRI_RESPONSIBLES, __('Responsibles of the alerted KRIs', 'erm'));
    }

    public function addSpecificTargets($data, $options)
    {
        if ((int) $data['type'] !== Notification::USER_TYPE) {
            return;
        }
        $alerts = $options['alerts'] ?? [];
        switch ((int) $data['items_id']) {
            case self::RISK_OWNER:
                $this->addUserByField('users_id');
                break;
            case self::RISK_GROUP:
                if (!empty($this->obj->fields['groups_id'])) {
                    $this->addForGroup(0, (int) $this->obj->fields['groups_id']);
                }
                break;
            case self::ACTION_RESPONSIBLES:
                $this->addUsers(array_column(array_filter($alerts, fn($a) => str_starts_with($a['type'], 'action_')), 'users_id'));
                break;
            case self::KRI_RESPONSIBLES:
                $this->addUsers(array_column(array_filter($alerts, fn($a) => str_starts_with($a['type'], 'kri_')), 'users_id'));
                break;
        }
    }

    /**
     * @param list<int|string> $ids
     */
    private function addUsers(array $ids): void
    {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return;
        }
        $criteria = $this->getDistinctUserCriteria() + $this->getProfileJoinCriteria();
        $criteria['FROM'] = User::getTable();
        $criteria['WHERE'][User::getTable() . '.id'] = $ids;
        foreach ($DB->request($criteria) as $user) {
            $this->addToRecipientsList($user);
        }
    }

    public function addDataForTemplate($event, $options = [])
    {
        $risk = $this->obj;
        if (!$risk instanceof Risk) {
            return;
        }
        $labels = self::alertLabels();
        $this->data['##erm.action##'] = $this->getEvents()[$event] ?? __('Corporate risk alerts', 'erm');
        $this->data['##erm.code##'] = $risk->fields['code'];
        $this->data['##erm.name##'] = $risk->fields['name'];
        $this->data['##erm.entity##'] = \Dropdown::getDropdownName('glpi_entities', (int) $risk->fields['entities_id']);
        $this->data['##erm.owner##'] = $risk->fields['users_id'] ? getUserName((int) $risk->fields['users_id']) : '';
        $this->data['##erm.residual##'] = Scale::levels()[$risk->fields['residual_level']] ?? '';
        $this->data['##erm.url##'] = $this->formatURL($options['additionnaloption']['usertype'] ?? self::GLPI_USER, Risk::class . '_' . $risk->getID());
        $this->data['##erm.alerts.number##'] = (string) count($options['alerts'] ?? []);
        $alerts = [];
        foreach ($options['alerts'] ?? [] as $a) {
            $alerts[] = [
                '##alert.type##'  => $labels[$a['type']] ?? $a['type'],
                '##alert.label##' => $a['label'],
                '##alert.date##'  => $a['date'] ?? '',
                '##alert.responsible##' => !empty($a['users_id']) ? getUserName((int) $a['users_id']) : '',
            ];
        }
        // List of alerts for the ##FOREACHalerts## block (same structure as core targets, e.g. contracts)
        // @phpstan-ignore assign.propertyType
        $this->data['alerts'] = $alerts;
        $this->getTags();
        foreach ($this->tag_descriptions[self::TAG_LANGUAGE] as $tag => $values) {
            if (!isset($this->data[$tag])) {
                $this->data[$tag] = $values['label'];
            }
        }
    }

    public function getTags()
    {
        $tags = [
            'erm.action' => __('Event', 'erm'), 'erm.code' => __('Code', 'erm'), 'erm.name' => __('Risk', 'erm'),
            'erm.entity' => \Entity::getTypeName(1), 'erm.owner' => __('Risk owner', 'erm'),
            'erm.residual' => __('Residual level', 'erm'), 'erm.alerts.number' => __('Number of alerts', 'erm'),
            'alert.type' => __('Alert', 'erm'), 'alert.label' => __('Detail', 'erm'), 'alert.date' => __('Date', 'erm'),
            'alert.responsible' => __('Responsible', 'erm'),
        ];
        foreach ($tags as $tag => $label) {
            $this->addTagToList(['tag' => $tag, 'label' => $label, 'value' => true]);
        }
        $this->addTagToList(['tag' => 'erm.url', 'label' => __('URL'), 'value' => true, 'lang' => false]);
        $this->addTagToList(['tag' => 'alerts', 'label' => __('Alerts', 'erm'), 'value' => false, 'foreach' => true]);
        asort($this->tag_descriptions);
    }
}

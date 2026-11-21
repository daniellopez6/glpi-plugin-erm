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

use CommonGLPI;
use CronTask;
use NotificationEvent;

/**
 * Daily automatic action "ermalerts": collects the alerts of each active risk and sends one e-mail digest per risk
 * (GLPI notification "Corporate risk alerts"). The same alert is repeated at most once a week.
 */
class Notifier extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Corporate risk alerts', 'erm');
    }

    public static function cronInfo($name)
    {
        return $name === 'ermalerts'
            ? ['description' => __('Corporate risk e-mail alerts (overdue actions and reviews, KRIs, risks above tolerance)', 'erm')]
            : [];
    }

    /**
     * @param CronTask $task
     */
    public static function cronErmalerts($task): int
    {
        $sent = 0;
        foreach (self::collect(date('Y-m-d')) as $riskId => $alerts) {
            $risk = new Risk();
            if (!$risk->getFromDB($riskId)) {
                continue;
            }
            $pending = array_values(array_filter($alerts, fn($a) => Reminder::shouldSend(self::lastSent($a), date('Y-m-d'))));
            if (!$pending) {
                continue;
            }
            if (NotificationEvent::raiseEvent(NotificationTargetRisk::EVENT, $risk, ['alerts' => $pending, 'entities_id' => $risk->fields['entities_id']])) {
                foreach ($pending as $a) {
                    self::markSent($a);
                }
                $sent++;
            }
        }
        $task->addVolume($sent);
        return $sent > 0 ? 1 : 0;
    }

    /** Immediate notice to the new owner of a risk (not when users assign themselves). */
    public static function ownerAssigned(Risk $risk): void
    {
        $owner = (int) $risk->fields['users_id'];
        if ($owner <= 0 || $owner === (int) \Session::getLoginUserID()) {
            return;
        }
        NotificationEvent::raiseEvent(NotificationTargetRisk::EVENT_OWNER, $risk, ['alerts' => [[
            'type' => 'owner_assigned', 'label' => (string) $risk->fields['name'], 'date' => $risk->fields['review_date'],
            'users_id' => $owner, 'itemtype' => Risk::class, 'items_id' => $risk->getID(),
        ]], 'entities_id' => $risk->fields['entities_id']]);
    }

    /** Immediate notice to the responsible of a treatment action (not when users assign themselves). */
    public static function actionAssigned(Action $action): void
    {
        $user = (int) $action->fields['users_id'];
        $risk = new Risk();
        if ($user <= 0 || $user === (int) \Session::getLoginUserID() || !$risk->getFromDB((int) $action->fields['plugin_erm_risks_id'])) {
            return;
        }
        NotificationEvent::raiseEvent(NotificationTargetRisk::EVENT_ACTION, $risk, ['alerts' => [[
            'type' => 'action_assigned', 'label' => (string) $action->fields['name'], 'date' => $action->fields['due_date'],
            'users_id' => $user, 'itemtype' => Action::class, 'items_id' => $action->getID(),
        ]], 'entities_id' => $risk->fields['entities_id']]);
    }

    /**
     * Notice to the risk owner when a treatment action is completed (not when the owner completed it). When it was
     * the last open action, the notice also says whether the risk reached its target.
     */
    public static function actionCompleted(Action $action): void
    {
        global $DB;
        $risk = new Risk();
        if (!$risk->getFromDB((int) $action->fields['plugin_erm_risks_id'])) {
            return;
        }
        $owner = (int) $risk->fields['users_id'];
        if ($owner <= 0 || $owner === (int) \Session::getLoginUserID()) {
            return;
        }
        $alerts = [[
            'type' => 'action_done', 'label' => (string) $action->fields['name'],
            'date' => $action->fields['completion_date'] ?: date('Y-m-d'),
            'users_id' => (int) $action->fields['users_id'], 'itemtype' => Action::class, 'items_id' => $action->getID(),
        ]];
        $total = countElementsInTable(Action::getTable(), ['plugin_erm_risks_id' => $risk->getID()]);
        $open = countElementsInTable(Action::getTable(), ['plugin_erm_risks_id' => $risk->getID(), 'NOT' => ['status' => ['done', 'cancelled']]]);
        if ($total > 0 && $open === 0) {
            $eval = TreatmentEval::evaluate(
                $total,
                $total,
                (int) ($risk->fields['residual_score'] ?: $risk->fields['inherent_score']),
                (int) $risk->fields['target_score']
            );
            $alerts[] = [
                'type' => 'plan_completed',
                'label' => match ($eval) {
                    'reached'     => __('All actions are closed and the risk is at its target level.', 'erm'),
                    'ineffective' => __('All actions are closed but the residual risk is above the target: update the assessment if the controls improved, or review the plan.', 'erm'),
                    default       => __('All actions are closed. Update the residual assessment and record a review.', 'erm'),
                },
                'date' => $risk->fields['target_date'], 'users_id' => $owner, 'itemtype' => Risk::class, 'items_id' => $risk->getID(),
            ];
        }
        NotificationEvent::raiseEvent(NotificationTargetRisk::EVENT_ACTION_DONE, $risk, ['alerts' => $alerts, 'entities_id' => $risk->fields['entities_id']]);
    }

    /**
     * Current alerts of every active risk (all entities: the cron runs without user restrictions).
     *
     * @return array<int, list<array{type:string, label:string, date:?string, users_id:int, itemtype:string, items_id:int}>>
     */
    public static function collect(string $today): array
    {
        global $DB;
        $out = [];
        $risks = iterator_to_array($DB->request(['FROM' => Risk::getTable(), 'WHERE' => ['is_deleted' => 0, 'NOT' => ['status' => 'closed']]]), false);
        $byId = array_column($risks, null, 'id');
        if (!$byId) {
            return $out;
        }
        $add = function (int $riskId, string $type, string $label, ?string $date, int $user, string $itemtype, int $itemId) use (&$out): void {
            $out[$riskId][] = ['type' => $type, 'label' => $label, 'date' => $date, 'users_id' => $user,
                                'itemtype' => $itemtype, 'items_id' => $itemId];
        };

        // Treatment actions overdue or due within a week
        foreach ($DB->request(['FROM' => Action::getTable(), 'WHERE' => ['plugin_erm_risks_id' => array_keys($byId), 'NOT' => ['status' => ['done', 'cancelled']]]]) as $a) {
            $type = Reminder::actionAlert($a['due_date'], $today);
            if ($type !== null) {
                $add((int) $a['plugin_erm_risks_id'], $type, (string) $a['name'], $a['due_date'], (int) $a['users_id'], Action::class, (int) $a['id']);
            }
        }
        foreach ($byId as $id => $r) {
            // Review overdue or due within 14 days
            $reviewAlert = Reminder::reviewAlert($r['review_date'], $today);
            if ($reviewAlert !== null) {
                $add($id, $reviewAlert, __('Review of the risk', 'erm'), $r['review_date'], (int) $r['users_id'], Risk::class, $id);
            }
            // Residual above appetite or tolerance
            [$app, $tol] = Category::effectiveAppetite((int) $r['plugin_erm_categories_id'], (int) $r['entities_id']);
            $appetiteAlert = Reminder::appetiteAlert(Scale::appetitePosition(DrillFilter::level($r), $app, $tol)[0]);
            if ($appetiteAlert !== null) {
                $add($id, $appetiteAlert, sprintf(__('Residual level: %s', 'erm'), Scale::levels()[DrillFilter::level($r)] ?? ''), null, (int) $r['users_id'], Risk::class, $id);
            }
            // KRIs: last value in alert, or measurement overdue
            foreach (Indicator::getForRisk((int) $id) as $k) {
                if (in_array($k['status'], ['orange', 'red'], true)) {
                    $add($id, 'kri_alert', sprintf('%s: %s %s', $k['name'], $k['last']['value'], $k['unit']), $k['last']['value_date'], (int) $k['users_id'], Indicator::class, (int) $k['id']);
                }
                $next = Schedule::nextMeasurement($k['last']['value_date'] ?? null, $k['frequency'], $today);
                if ($next < $today) {
                    $add($id, 'kri_measure', (string) $k['name'], $next, (int) $k['users_id'], Indicator::class, (int) $k['id']);
                }
            }
        }
        // Treatment completed without reaching the target
        foreach (Dashboard::treatments($risks) as $id => $eval) {
            if ($eval === 'ineffective') {
                $add($id, 'ineffective', __('All actions are closed but the residual risk is above the target', 'erm'), $byId[$id]['target_date'] ?: null, (int) $byId[$id]['users_id'], Risk::class, $id);
            }
        }
        // Assessment below what the loss history suggests
        foreach (Event::historyOf($risks) as $id => $h) {
            if ($h['underestimated']) {
                $add($id, 'history', sprintf(__('%d loss events in %d years', 'erm'), $h['losses'], LossStats::WINDOW_YEARS), null, (int) $byId[$id]['users_id'], Risk::class, $id);
            }
        }
        return $out;
    }

    private static function lastSent(array $alert): ?string
    {
        global $DB;
        $row = $DB->request(['FROM' => 'glpi_plugin_erm_alerts', 'WHERE' => [
            'itemtype' => $alert['itemtype'], 'items_id' => $alert['items_id'], 'alert_type' => $alert['type'],
        ]])->current();
        return $row['date_sent'] ?? null;
    }

    private static function markSent(array $alert): void
    {
        global $DB;
        $DB->updateOrInsert('glpi_plugin_erm_alerts', ['date_sent' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s')], [
            'itemtype' => $alert['itemtype'], 'items_id' => $alert['items_id'], 'alert_type' => $alert['type'],
        ]);
    }
}

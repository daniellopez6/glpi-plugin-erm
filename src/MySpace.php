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
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Personal workspace: my risks, my pending actions, the KRIs I measure and the reviews I have to do.
 */
class MySpace extends CommonGLPI
{
    public static string $rightname = Rights::RISK;

    public static function getTypeName($nb = 0)
    {
        return __('My ERM workspace', 'erm');
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    public static function getIcon()
    {
        return 'ti ti-user-check';
    }

    public static function canView(): bool
    {
        return Risk::canView();
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/myspace.php', $full);
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        return ['title' => self::getMenuName(), 'page' => self::getSearchURL(false), 'icon' => self::getIcon()];
    }

    public static function show(): void
    {
        global $DB;
        $uid = (int) Session::getLoginUserID();
        $today = date('Y-m-d');
        $groups = array_map('intval', $_SESSION['glpigroups'] ?? []);
        $entities = getEntitiesRestrictCriteria('r', '', '', true);

        // Risks I own (directly or through one of my groups)
        $owner = ['r.users_id' => $uid];
        if ($groups) {
            $owner = ['OR' => $owner + ['r.groups_id' => $groups]];
        }
        $risks = [];
        $iter = $DB->request([
           'SELECT'    => ['r.*', 'c.completename AS category'],
           'FROM'      => Risk::getTable() . ' AS r',
           'LEFT JOIN' => [Category::getTable() . ' AS c' => ['ON' => ['r' => 'plugin_erm_categories_id', 'c' => 'id']]],
           'WHERE'     => ['r.is_deleted' => 0, 'NOT' => ['r.status' => 'closed'], $owner] + $entities,
           'ORDER'     => ['r.residual_score DESC', 'r.code'],
        ]);
        foreach ($iter as $r) {
            [$app, $tol] = Category::effectiveAppetite((int) $r['plugin_erm_categories_id'], (int) $r['entities_id']);
            $r['residual_badge'] = Scale::badge(DrillFilter::level($r), (int) ($r['residual_score'] ?: $r['inherent_score']) ?: null);
            $r['appetite_badge'] = Scale::appetiteBadge(Scale::appetitePosition(DrillFilter::level($r), $app, $tol));
            $r['review_state'] = Schedule::state($r['review_date'], $today);
            $r['status_label'] = Scale::statuses()[$r['status']] ?? $r['status'];
            $risks[] = $r;
        }

        // Pending actions assigned to me
        $actions = [];
        $iter = $DB->request([
           'SELECT'     => ['a.*', 'r.code', 'r.name AS risk_name'],
           'FROM'       => Action::getTable() . ' AS a',
           'INNER JOIN' => [Risk::getTable() . ' AS r' => ['ON' => ['a' => 'plugin_erm_risks_id', 'r' => 'id']]],
           'WHERE'      => ['a.users_id' => $uid, 'r.is_deleted' => 0, 'NOT' => ['a.status' => ['done', 'cancelled']]] + $entities,
           'ORDER'      => [new \Glpi\DBAL\QueryExpression('a.due_date IS NULL'), 'a.due_date'],
        ]);
        foreach ($iter as $a) {
            $a['state'] = Schedule::state($a['due_date'], $today);
            $actions[] = $a;
        }

        // KRIs I measure
        $kris = [];
        $iter = $DB->request([
           'SELECT'     => ['k.id', 'r.id AS risk_id', 'r.code'],
           'FROM'       => Indicator::getTable() . ' AS k',
           'INNER JOIN' => [Risk::getTable() . ' AS r' => ['ON' => ['k' => 'plugin_erm_risks_id', 'r' => 'id']]],
           'WHERE'      => ['k.users_id' => $uid, 'r.is_deleted' => 0, 'NOT' => ['r.status' => 'closed']] + $entities,
        ]);
        $byRisk = [];
        foreach ($iter as $row) {
            $byRisk[(int) $row['risk_id']][(int) $row['id']] = $row['code'];
        }
        foreach ($byRisk as $riskId => $ids) {
            foreach (Indicator::getForRisk($riskId) as $k) {
                if (!isset($ids[(int) $k['id']])) {
                    continue;
                }
                $k['risk_id'] = $riskId;
                $k['risk_code'] = $ids[(int) $k['id']];
                $k['next'] = Schedule::nextMeasurement($k['last']['value_date'] ?? null, $k['frequency'], $today);
                $k['state'] = Schedule::state($k['next'], $today, 7);
                $kris[] = $k;
            }
        }
        usort($kris, fn($a, $b) => $a['next'] <=> $b['next']);

        TemplateRenderer::getInstance()->display('@erm/myspace.html.twig', [
           'user'         => getUserName($uid),
           'risks'        => $risks,
           'actions'      => $actions,
           'kris'         => $kris,
           'counters'     => [
               'risks'          => count($risks),
               'actions'        => count($actions),
               'overdue'        => count(array_filter($actions, fn($a) => $a['state'] === 'overdue')),
               'kris_due'       => count(array_filter($kris, fn($k) => in_array($k['state'], ['overdue', 'soon'], true))),
               'reviews_due'    => count(array_filter($risks, fn($r) => in_array($r['review_state'], ['overdue', 'soon'], true))),
           ],
           'statuses'     => Scale::actionStatuses(),
           'frequencies'  => Indicator::frequencies(),
           'risk_url'     => Risk::getFormURL(),
           'action_url'   => Action::getFormURL(),
           'kri_post_url' => plugin_erm_url('front/indicator.form.php'),
           'can_update_actions' => Session::haveRight(Rights::TREATMENT, UPDATE),
           'can_measure'  => Session::haveRight(Rights::TREATMENT, CREATE),
           'today'        => $today,
           'report_url'   => Concern::getSearchURL(),
           'wizard_url'   => Risk::canCreate() ? RiskWizard::getWizardURL() : '',
        ]);
    }
}

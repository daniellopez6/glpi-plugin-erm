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

/**
 * Quarterly snapshots of the risk profile, used to compare periods in the Board report.
 * The automatic action "ermsnapshot" takes the snapshot of the quarter the first time it runs in it.
 */
class Snapshot extends CommonGLPI
{
    public const TABLE = 'glpi_plugin_erm_snapshots';

    public static function getTypeName($nb = 0)
    {
        return _n('Risk snapshot', 'Risk snapshots', $nb, 'erm');
    }

    public static function cronInfo($name)
    {
        return $name === 'ermsnapshot' ? ['description' => __('Quarterly snapshot of the corporate risk profile', 'erm')] : [];
    }

    /**
     * @param CronTask $task
     */
    public static function cronErmsnapshot($task): int
    {
        $period = Period::quarterOf(date('Y-m-d'));
        if (countElementsInTable(self::TABLE, ['period' => $period]) > 0) {
            return 0;
        }
        $n = self::take($period);
        $task->addVolume($n);
        return 1;
    }

    /**
     * Records the snapshot of a period (all entities), replacing a previous one of the same period.
     */
    public static function take(string $period): int
    {
        global $DB;
        if (!Period::isValid($period)) {
            return 0;
        }
        $DB->delete(self::TABLE, ['period' => $period]);
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $n = 0;
        $iter = $DB->request([
            'SELECT'    => ['r.*', 'c.completename AS category'],
            'FROM'      => Risk::getTable() . ' AS r',
            'LEFT JOIN' => [Category::getTable() . ' AS c' => ['ON' => ['r' => 'plugin_erm_categories_id', 'c' => 'id']]],
            'WHERE'     => ['r.is_deleted' => 0, 'NOT' => ['r.status' => 'closed']],
        ]);
        foreach ($iter as $r) {
            [$app, $tol] = Category::effectiveAppetite((int) $r['plugin_erm_categories_id'], (int) $r['entities_id']);
            $DB->insert(self::TABLE, [
                'period'               => $period,
                'snapshot_date'        => $now,
                'plugin_erm_risks_id'  => $r['id'],
                'entities_id'          => $r['entities_id'],
                'code'                 => $r['code'],
                'name'                 => $r['name'],
                'category'             => (string) $r['category'],
                'users_id'             => $r['users_id'],
                'inherent_score'       => $r['inherent_score'],
                'inherent_level'       => $r['inherent_level'],
                'residual_score'       => $r['residual_score'],
                'residual_level'       => $r['residual_level'],
                'target_score'         => $r['target_score'],
                'target_level'         => $r['target_level'],
                'appetite_position'    => Scale::appetitePosition(DrillFilter::level($r), $app, $tol)[0],
                'economic_impact_max'  => $r['economic_impact_max'],
                'status'               => $r['status'],
                'treatment'            => $r['treatment'],
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * Available periods with their number of risks (visible entities), most recent first.
     *
     * @return array<string, array{count:int, date:string}>
     */
    public static function periods(): array
    {
        global $DB;
        $out = [];
        $iter = $DB->request([
            'SELECT' => ['period', 'COUNT' => 'id AS n', 'MAX' => 'snapshot_date AS d'],
            'FROM'   => self::TABLE,
            'WHERE'  => getEntitiesRestrictCriteria(self::TABLE, '', '', true),
            'GROUPBY' => 'period',
            'ORDER'  => 'period DESC',
        ]);
        foreach ($iter as $row) {
            $out[(string) $row['period']] = ['count' => (int) $row['n'], 'date' => (string) $row['d']];
        }
        return $out;
    }

    /**
     * Snapshot rows of a period in the visible entities, indexed by risk id.
     *
     * @param list<int>|null $onlyRiskIds restrict to these risks (users who only see own risks)
     * @return array<int, array<string,mixed>>
     */
    public static function load(string $period, ?array $onlyRiskIds = null): array
    {
        global $DB;
        if (!Period::isValid($period)) {
            return [];
        }
        $where = ['period' => $period] + getEntitiesRestrictCriteria(self::TABLE, '', '', true);
        if ($onlyRiskIds !== null) {
            if (!$onlyRiskIds) {
                return [];
            }
            $where['plugin_erm_risks_id'] = $onlyRiskIds;
        }
        $out = [];
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => $where]) as $row) {
            $row['id'] = (int) $row['plugin_erm_risks_id'];
            $out[$row['id']] = $row;
        }
        return $out;
    }
}

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

use Plugin;

/**
 * Effectiveness of the GLPI GRC Manager controls that protect a corporate risk: controls linked directly to the
 * risk and controls of the ISMS risks linked to it, with the result of their last test. Read only.
 */
final class ControlFeed
{
    public const GRC_CONTROL = 'GlpiPlugin\\Grcmanager\\Control';

    public static function available(): bool
    {
        global $DB;
        return Plugin::isPluginActive('grcmanager') && $DB->tableExists('glpi_plugin_grcmanager_controltests')
            && $DB->tableExists('glpi_plugin_grcmanager_controls_risks');
    }

    /**
     * Controls of each risk with their last test.
     *
     * @param list<int> $riskIds
     * @return array<int, list<array{id:int, code:string, result:?string, test_date:?string}>>
     */
    public static function controlsByRisk(array $riskIds): array
    {
        global $DB;
        $out = [];
        if (!$riskIds || !self::available()) {
            return $out;
        }
        $controls = [];
        // Controls linked directly to the corporate risk
        foreach ($DB->request(['FROM' => Risk_Item::getTable(), 'WHERE' => ['plugin_erm_risks_id' => $riskIds, 'itemtype' => self::GRC_CONTROL]]) as $l) {
            $controls[(int) $l['plugin_erm_risks_id']][(int) $l['items_id']] = true;
        }
        // Controls of the ISMS risks linked to the corporate risk
        $iter = $DB->request([
            'SELECT'     => ['l.plugin_erm_risks_id', 'cr.plugin_grcmanager_controls_id'],
            'FROM'       => Risk_Item::getTable() . ' AS l',
            'INNER JOIN' => ['glpi_plugin_grcmanager_controls_risks AS cr' => ['ON' => ['l' => 'items_id', 'cr' => 'plugin_grcmanager_risks_id']]],
            'WHERE'      => ['l.plugin_erm_risks_id' => $riskIds, 'l.itemtype' => Consolidated::GRC_RISK],
        ]);
        foreach ($iter as $row) {
            $controls[(int) $row['plugin_erm_risks_id']][(int) $row['plugin_grcmanager_controls_id']] = true;
        }
        $ids = array_keys(array_replace(...array_values($controls ?: [[]])));
        if (!$ids) {
            return $out;
        }
        $codes = [];
        foreach ($DB->request(['SELECT' => ['id', 'code'], 'FROM' => 'glpi_plugin_grcmanager_controls', 'WHERE' => ['id' => $ids]]) as $c) {
            $codes[(int) $c['id']] = (string) $c['code'];
        }
        $last = [];
        $tests = $DB->request(['FROM' => 'glpi_plugin_grcmanager_controltests', 'WHERE' => ['plugin_grcmanager_controls_id' => $ids],
                               'ORDER' => ['test_date DESC', 'id DESC']]);
        foreach ($tests as $t) {
            $last[(int) $t['plugin_grcmanager_controls_id']] ??= ['result' => $t['result'], 'test_date' => $t['test_date']];
        }
        foreach ($controls as $riskId => $set) {
            foreach (array_keys($set) as $cid) {
                if (!isset($codes[$cid])) {
                    continue;
                }
                $out[$riskId][] = ['id' => $cid, 'code' => $codes[$cid], 'result' => $last[$cid]['result'] ?? null,
                                   'test_date' => $last[$cid]['test_date'] ?? null];
            }
            usort($out[$riskId], fn($a, $b) => strnatcmp($a['code'], $b['code']));
        }
        return $out;
    }

    /**
     * @param list<int> $riskIds
     * @return array<int, array<string,mixed>> ControlEval summary per risk, with the failing controls
     */
    public static function summaryByRisk(array $riskIds): array
    {
        $out = [];
        foreach (self::controlsByRisk($riskIds) as $riskId => $controls) {
            $out[$riskId] = ControlEval::summarize(array_column($controls, 'result')) + [
                'failing_codes' => array_column(array_filter($controls, fn($c) => $c['result'] === 'ineffective'), 'code'),
                'partial_codes' => array_column(array_filter($controls, fn($c) => $c['result'] === 'partial'), 'code'),
            ];
        }
        return $out;
    }
}

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
 * ERM dashboard: inherent/residual heat maps, economic exposure, appetite and overdue actions.
 */
class Dashboard extends CommonGLPI
{
    public static string $rightname = 'plugin_erm';

    public static function getTypeName($nb = 0)
    {
        return __('Corporate risks', 'erm');
    }

    public static function getMenuName()
    {
        return __('Corporate risks (ERM)', 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-chart-dots-3';
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/dashboard.php', $full);
    }

    public static function getMenuContent()
    {
        if (!Risk::canView()) {
            return false;
        }
        return [
           'title' => self::getMenuName(),
           'page'  => self::getSearchURL(false),
           'icon'  => self::getIcon(),
           'options' => [
               Risk::class => [
                   'title' => Risk::getTypeName(2),
                   'page'  => Risk::getSearchURL(false),
                   'links' => ['search' => Risk::getSearchURL(false)] + (Session::haveRight(Rights::RISK, CREATE) ? ['add' => Risk::getFormURL(false)] : []),
               ],
               Category::class => [
                   'title' => Category::getTypeName(2),
                   'page'  => Category::getSearchURL(false),
                   'links' => ['search' => Category::getSearchURL(false)] + (Session::haveRight(Rights::CONFIG, CREATE) ? ['add' => Category::getFormURL(false)] : []),
               ],
           ],
        ];
    }

    public static function show(): void
    {
        global $DB;
        $risks = Risk::activeRisks();
        $heat = ['inherent' => [], 'residual' => []];
        $byLevel = array_fill_keys(Scale::LEVELS, 0);
        $byCat = [];
        $exposure = 0.0;
        $outside = [];
        foreach ($risks as $r) {
            foreach (['inherent', 'residual'] as $k) {
                $p = (int) $r["{$k}_probability"];
                $i = (int) $r["{$k}_impact"];
                if ($p && $i) {
                    $heat[$k][$p][$i][] = $r['code'];
                }
            }
            $lvl = $r['residual_level'] ?: $r['inherent_level'];
            if (isset($byLevel[$lvl])) {
                $byLevel[$lvl]++;
            }
            $root = explode(' > ', (string) $r['category'])[0] ?: __('Uncategorized', 'erm');
            $byCat[$root] ??= ['count' => 0, 'exposure' => 0.0, 'levels' => array_fill_keys(Scale::LEVELS, 0)];
            $byCat[$root]['count']++;
            $byCat[$root]['exposure'] += (float) $r['economic_impact_max'];
            if (isset($byCat[$root]['levels'][$lvl])) {
                $byCat[$root]['levels'][$lvl]++;
            }
            $exposure += (float) $r['economic_impact_max'];
            if (in_array($r['appetite'][0], ['above_appetite', 'above_tolerance'], true)) {
                $outside[] = $r;
            }
        }
        uasort($byCat, fn($a, $b) => $b['exposure'] <=> $a['exposure']);

        // Per entity breakdown (consultant view across several companies)
        $byEntity = [];
        foreach ($risks as $r) {
            $eid = (int) $r['entities_id'];
            $byEntity[$eid] ??= ['name' => \Dropdown::getDropdownName('glpi_entities', $eid), 'count' => 0, 'exposure' => 0.0,
                                 'levels' => array_fill_keys(Scale::LEVELS, 0), 'outside' => 0, 'overdue' => 0, 'kri' => 0,
                                 'url' => Risk::getSearchURL() . '?' . http_build_query(['criteria' => [[
                                     'field' => 80, 'searchtype' => 'equals', 'value' => $eid,
                                 ]]])];
            $byEntity[$eid]['count']++;
            $byEntity[$eid]['exposure'] += (float) $r['economic_impact_max'];
            $lvl = $r['residual_level'] ?: $r['inherent_level'];
            if (isset($byEntity[$eid]['levels'][$lvl])) {
                $byEntity[$eid]['levels'][$lvl]++;
            }
            if (in_array($r['appetite'][0], ['above_appetite', 'above_tolerance'], true)) {
                $byEntity[$eid]['outside']++;
            }
        }
        $riskEntity = array_column($risks, 'entities_id', 'id');
        $maxExp = max(array_merge([1.0], array_column($byCat, 'exposure')));

        $overdue = iterator_to_array($DB->request([
           'SELECT' => ['a.*', 'r.code', 'r.name AS risk_name', 'r.id AS risk_id'],
           'FROM' => Action::getTable() . ' AS a',
           'INNER JOIN' => [Risk::getTable() . ' AS r' => ['ON' => ['a' => 'plugin_erm_risks_id', 'r' => 'id']]],
           'WHERE' => ['r.is_deleted' => 0, 'a.due_date' => ['<', date('Y-m-d')], 'NOT' => ['a.status' => ['done', 'cancelled']]]
               + getEntitiesRestrictCriteria('r', '', '', true) + (Rights::onlyOwn() ? [Rights::ownCriteria('r')] : []),
           'ORDER' => 'a.due_date',
        ]), false);
        foreach ($overdue as &$a) {
            $a['owner'] = $a['users_id'] ? getUserName($a['users_id']) : '';
            $eid = (int) ($riskEntity[$a['risk_id']] ?? -1);
            if (isset($byEntity[$eid])) {
                $byEntity[$eid]['overdue']++;
            }
        }
        unset($a);

        $kris = [];
        foreach ($risks as $r) {
            foreach (Indicator::getForRisk((int) $r['id']) as $k) {
                if (in_array($k['status'], ['orange', 'red'], true)) {
                    $byEntity[(int) $r['entities_id']]['kri']++;
                    $k['risk_code'] = $r['code'];
                    $k['risk_id'] = $r['id'];
                    $kris[] = $k;
                }
            }
        }

        $cells = [];
        foreach (range(5, 1) as $p) {
            foreach (range(1, 5) as $i) {
                $cells[$p][$i] = Scale::color(Scale::levelFor($p, $i));
            }
        }

        TemplateRenderer::getInstance()->display('@erm/dashboard.html.twig', [
           'total'       => count($risks),
           'by_level'    => $byLevel,
           'levels'      => Scale::levels(),
           'level_color' => array_combine(Scale::LEVELS, array_map([Scale::class, 'color'], Scale::LEVELS)),
           'heat'        => $heat,
           'cells'       => $cells,
           'prob_labels' => Scale::probabilities(),
           'imp_labels'  => Scale::impacts(),
           'by_cat'      => $byCat,
           'max_exp'     => $maxExp,
           'exposure'    => Scale::money($exposure),
           'outside'     => $outside,
           'overdue'     => $overdue,
           'kris'        => $kris,
           'top'         => array_slice($risks, 0, 10),
           'risk_url'    => Risk::getFormURL(),
           'list_url'    => Risk::getSearchURL(),
           'grc'         => class_exists('GlpiPlugin\\Grcmanager\\Risk'),
           'only_own'    => Rights::onlyOwn(),
           'by_entity'   => count($byEntity) > 1 ? $byEntity : [],
        ]);
    }
}

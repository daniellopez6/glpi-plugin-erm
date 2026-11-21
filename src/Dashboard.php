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
               Action::class => [
                   'title' => __('Action plans', 'erm'),
                   'page'  => Action::getSearchURL(false),
                   'links' => ['search' => Action::getSearchURL(false)],
               ],
               Category::class => [
                   'title' => Category::getTypeName(2),
                   'page'  => Category::getSearchURL(false),
                   'links' => ['search' => Category::getSearchURL(false)] + (Session::haveRight(Rights::CONFIG, CREATE) ? ['add' => Category::getFormURL(false)] : []),
               ],
           ],
        ];
    }

    /** URL of the drill-down list for the given filters. */
    public static function drillURL(array $filters = []): string
    {
        return plugin_erm_url('front/dashboard.php') . '?' . http_build_query(['list' => 1] + $filters);
    }

    /**
     * Overdue actions and KRIs in alert of the given risks.
     *
     * @param list<array<string,mixed>> $risks
     * @return array{0: list<array<string,mixed>>, 1: list<array<string,mixed>>}
     */
    private static function alerts(array $risks): array
    {
        global $DB;
        $byId = array_column($risks, null, 'id');
        $overdue = [];
        if ($byId) {
            $iter = $DB->request([
               'SELECT' => ['a.*', 'r.code', 'r.name AS risk_name', 'r.id AS risk_id'],
               'FROM' => Action::getTable() . ' AS a',
               'INNER JOIN' => [Risk::getTable() . ' AS r' => ['ON' => ['a' => 'plugin_erm_risks_id', 'r' => 'id']]],
               'WHERE' => ['r.id' => array_keys($byId), 'a.due_date' => ['<', date('Y-m-d')], 'NOT' => ['a.status' => ['done', 'cancelled']]],
               'ORDER' => 'a.due_date',
            ]);
            foreach ($iter as $a) {
                $a['owner'] = $a['users_id'] ? getUserName($a['users_id']) : '';
                $overdue[] = $a;
            }
        }
        $kris = [];
        foreach ($risks as $r) {
            foreach (Indicator::getForRisk((int) $r['id']) as $k) {
                if (in_array($k['status'], ['orange', 'red'], true)) {
                    $k['risk_code'] = $r['code'];
                    $k['risk_id'] = $r['id'];
                    $kris[] = $k;
                }
            }
        }
        return [$overdue, $kris];
    }

    public static function show(): void
    {
        $risks = Risk::activeRisks();
        [$overdue, $kris] = self::alerts($risks);
        $heat = ['inherent' => [], 'residual' => []];
        $byLevel = array_fill_keys(Scale::LEVELS, 0);
        $byCat = [];
        $byEntity = [];
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
            $lvl = DrillFilter::level($r);
            $isOutside = in_array($r['appetite'][0], ['above_appetite', 'above_tolerance'], true);
            if (isset($byLevel[$lvl])) {
                $byLevel[$lvl]++;
            }
            $root = DrillFilter::rootCategory($r);
            $byCat[$root] ??= ['count' => 0, 'exposure' => 0.0, 'levels' => array_fill_keys(Scale::LEVELS, 0),
                               'name' => $root ?: __('Uncategorized', 'erm'), 'url' => self::drillURL(['category' => $root])];
            $byCat[$root]['count']++;
            $byCat[$root]['exposure'] += (float) $r['economic_impact_max'];
            if (isset($byCat[$root]['levels'][$lvl])) {
                $byCat[$root]['levels'][$lvl]++;
            }

            // Per entity breakdown (consultant view across several companies)
            $eid = (int) $r['entities_id'];
            $byEntity[$eid] ??= ['id' => $eid, 'name' => \Dropdown::getDropdownName('glpi_entities', $eid), 'count' => 0,
                                 'exposure' => 0.0, 'levels' => array_fill_keys(Scale::LEVELS, 0), 'outside' => 0,
                                 'overdue' => 0, 'kri' => 0];
            $byEntity[$eid]['count']++;
            $byEntity[$eid]['exposure'] += (float) $r['economic_impact_max'];
            if (isset($byEntity[$eid]['levels'][$lvl])) {
                $byEntity[$eid]['levels'][$lvl]++;
            }
            if ($isOutside) {
                $byEntity[$eid]['outside']++;
                $outside[] = $r;
            }
            $exposure += (float) $r['economic_impact_max'];
        }
        uasort($byCat, fn($a, $b) => $b['exposure'] <=> $a['exposure']);
        $riskEntity = array_column($risks, 'entities_id', 'id');
        foreach (array_unique(array_column($overdue, 'risk_id')) as $rid) {
            $byEntity[(int) $riskEntity[$rid]]['overdue']++;
        }
        foreach (array_unique(array_column($kris, 'risk_id')) as $rid) {
            $byEntity[(int) $riskEntity[$rid]]['kri']++;
        }
        $maxExp = max(array_merge([1.0], array_column($byCat, 'exposure')));

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
           'overdue_risks' => count(array_unique(array_column($overdue, 'risk_id'))),
           'kris'        => $kris,
           'top'         => array_slice($risks, 0, 10),
           'risk_url'    => Risk::getFormURL(),
           'list_url'    => Risk::getSearchURL(),
           'drill_url'   => self::drillURL(),
           'actions_url' => Action::getSearchURL(),
           'actions_form_url' => Action::getFormURL(),
           'grc'         => class_exists('GlpiPlugin\\Grcmanager\\Risk'),
           'only_own'    => Rights::onlyOwn(),
           'by_entity'   => count($byEntity) > 1 ? $byEntity : [],
        ]);
    }

    /**
     * Drill-down: list of the risks behind a dashboard figure.
     *
     * @param array<string,mixed> $query request parameters
     */
    public static function showList(array $query): void
    {
        $filters = DrillFilter::fromQuery($query);
        $risks = Risk::activeRisks();
        [$overdue, $kris] = self::alerts($risks);
        $rows = DrillFilter::apply(
            $risks,
            $filters,
            array_map('intval', array_column($overdue, 'risk_id')),
            array_map('intval', array_column($kris, 'risk_id'))
        );

        $chips = [];
        $levels = Scale::levels();
        if (isset($filters['level'])) {
            $chips[] = sprintf(__('Residual level: %s', 'erm'), $levels[$filters['level']]);
        }
        if (isset($filters['heat'])) {
            $chips[] = sprintf(
                $filters['heat'] === 'inherent' ? __('Inherent: probability %1$s × impact %2$s', 'erm') : __('Residual: probability %1$s × impact %2$s', 'erm'),
                Scale::probabilities()[$filters['p']],
                Scale::impacts()[$filters['i']]
            );
        }
        if (isset($filters['category'])) {
            $chips[] = sprintf(__('Category: %s', 'erm'), $filters['category'] ?: __('Uncategorized', 'erm'));
        }
        if (isset($filters['entity'])) {
            $chips[] = sprintf(__('Entity: %s', 'erm'), \Dropdown::getDropdownName('glpi_entities', $filters['entity']));
        }
        if (isset($filters['outside'])) {
            $chips[] = match ($filters['outside']) {
                'above_tolerance' => __('Above tolerance', 'erm'),
                'above_appetite'  => __('Above appetite', 'erm'),
                default           => __('Outside appetite', 'erm'),
            };
        }
        if (isset($filters['overdue'])) {
            $chips[] = __('With overdue actions', 'erm');
        }
        if (isset($filters['kri'])) {
            $chips[] = __('With KRIs in alert', 'erm');
        }

        foreach ($rows as &$r) {
            $r['owner'] = $r['users_id'] ? getUserName($r['users_id']) : '';
            $r['entity'] = \Dropdown::getDropdownName('glpi_entities', (int) $r['entities_id']);
            $r['inherent_badge'] = Scale::badge($r['inherent_level'], (int) $r['inherent_score'] ?: null);
            $r['residual_badge'] = Scale::badge($r['residual_level'], (int) $r['residual_score'] ?: null);
            $r['appetite_badge'] = Scale::appetiteBadge($r['appetite']);
            $r['status_label'] = Scale::statuses()[$r['status']] ?? $r['status'];
        }
        unset($r);

        TemplateRenderer::getInstance()->display('@erm/risk_list.html.twig', [
           'rows'          => $rows,
           'chips'         => $chips,
           'exposure'      => Scale::money(array_sum(array_map('floatval', array_column($rows, 'economic_impact_max')))),
           'risk_url'      => Risk::getFormURL(),
           'dashboard_url' => self::getSearchURL(),
           'multi_entity'  => count(array_unique(array_column($risks, 'entities_id'))) > 1,
        ]);
    }
}

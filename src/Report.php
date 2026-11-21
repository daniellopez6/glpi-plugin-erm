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
use GLPIPDF;
use Session;

/**
 * Board report (ISO 31000 6.7): risk profile of the visible entities compared with a previous quarterly snapshot,
 * exported as PDF.
 */
class Report extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Board report', 'erm');
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    public static function getIcon()
    {
        return 'ti ti-file-type-pdf';
    }

    public static function canView(): bool
    {
        return Risk::canView();
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/report.php', $full);
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        return ['title' => self::getMenuName(), 'page' => self::getSearchURL(false), 'icon' => self::getIcon()];
    }

    public static function canTakeSnapshot(): bool
    {
        return Session::haveRight(Rights::CONFIG, UPDATE);
    }

    /** Page: available snapshots, manual snapshot and PDF download. */
    public static function show(): void
    {
        $periods = Snapshot::periods();
        $current = Period::quarterOf(date('Y-m-d'));
        $compare = isset($periods[Period::previous($current)]) ? Period::previous($current) : (string) array_key_first(array_diff_key($periods, [$current => 1]));
        TemplateRenderer::getInstance()->display('@erm/report.html.twig', [
            'periods'  => $periods,
            'current'  => $current,
            'compare'  => $compare,
            'labels'   => array_combine(array_keys($periods), array_map([Period::class, 'label'], array_keys($periods))),
            'can_take' => self::canTakeSnapshot(),
            'action'   => self::getSearchURL(),
            'entity'   => \Dropdown::getDropdownName('glpi_entities', (int) ($_SESSION['glpiactive_entity'] ?? 0)),
        ]);
    }

    /**
     * Data of the report.
     *
     * @return array<string,mixed>
     */
    public static function data(string $compare): array
    {
        $risks = Risk::activeRisks();
        $current = [];
        foreach ($risks as $r) {
            $r['appetite_position'] = $r['appetite'][0];
            $current[(int) $r['id']] = $r;
        }
        $before = Period::isValid($compare) ? Snapshot::load($compare, Rights::onlyOwn() ? array_keys($current) : null) : [];
        [$overdue, $kris] = Dashboard::alerts($risks);
        $byLevel = fn(array $rows) => array_count_values(array_filter(array_map([SnapshotDiff::class, 'level'], $rows)));
        $outside = fn(array $rows) => count(array_filter($rows, fn($r) => in_array($r['appetite_position'] ?? '', ['above_appetite', 'above_tolerance'], true)));
        $exposure = fn(array $rows) => array_sum(array_map(fn($r) => (float) $r['economic_impact_max'], $rows));
        $heat = [];
        foreach ($risks as $r) {
            if ((int) $r['residual_probability'] && (int) $r['residual_impact']) {
                $heat[(int) $r['residual_probability']][(int) $r['residual_impact']] = ($heat[(int) $r['residual_probability']][(int) $r['residual_impact']] ?? 0) + 1;
            }
        }
        $underestimated = Event::canView() ? count(array_filter(Event::historyOf($risks), fn($h) => $h['underestimated'])) : null;
        return [
            'compare'   => $before ? $compare : '',
            'kpi'       => [
                'now'    => ['count' => count($current), 'levels' => $byLevel($current), 'outside' => $outside($current), 'exposure' => $exposure($current)],
                'before' => $before ? ['count' => count($before), 'levels' => $byLevel($before), 'outside' => $outside($before), 'exposure' => $exposure($before)] : null,
            ],
            'diff'      => SnapshotDiff::compare($before, $current),
            'heat'      => $heat,
            'outside'   => array_values(array_filter($risks, fn($r) => in_array($r['appetite'][0], ['above_appetite', 'above_tolerance'], true))),
            'top'       => array_slice($risks, 0, 10),
            'treatment' => array_count_values(Dashboard::treatments($risks)),
            'overdue'   => $overdue,
            'kris'      => $kris,
            'net_loss'  => Event::canView() ? Event::netLossSince(date('Y-m-d', strtotime('-12 months'))) : null,
            'underestimated' => $underestimated,
            'integrated' => Consolidated::data(),
        ];
    }

    /** Sends the PDF of the report to the browser. */
    public static function pdf(string $compare): void
    {
        $d = self::data($compare);
        $title = sprintf(__('Corporate risk report - %s', 'erm'), Period::label(Period::quarterOf(date('Y-m-d'))));
        // GLPIPDF prints the number of items in its footer and requires it to be set
        $pdf = new GLPIPDF(['orientation' => 'P', 'font_size' => 9], (int) $d['kpi']['now']['count'], $title);
        $pdf->SetTitle($title);
        $pdf->writeHTML(self::html($d, $title), true, false, true, false, '');
        $pdf->Output('erm-board-report-' . date('Y-m-d') . '.pdf', 'D');
    }

    /**
     * HTML (TCPDF subset) of the report. All values are escaped.
     *
     * @param array<string,mixed> $d
     */
    public static function html(array $d, string $title): string
    {
        $e = fn($v) => htmlescape((string) $v);
        $levels = Scale::levels();
        $badge = fn(string $lvl) => $lvl === '' ? '-' : sprintf(
            '<span style="background-color:%s;color:%s;"> %s </span>',
            Scale::color($lvl),
            $lvl === 'medium' ? '#1d273b' : '#ffffff',
            $e($levels[$lvl] ?? $lvl)
        );
        $h2 = fn(string $t) => '<h3 style="color:#1d273b;border-bottom:1px solid #999;">' . $e($t) . '</h3>';
        $th = 'style="background-color:#e9ecef;font-weight:bold;"';
        $money = fn($v) => $e(Scale::money((float) $v));
        $k = $d['kpi'];
        $delta = function ($now, $before) use ($e): string {
            if ($before === null) {
                return '';
            }
            $diff = $now - $before;
            return $diff == 0 ? ' <span style="color:#666;">(=)</span>' : ' <span style="color:' . ($diff > 0 ? '#d63939' : '#2fb344') . ';">(' . ($diff > 0 ? '+' : '') . $e(is_float($diff) ? Scale::money($diff) : $diff) . ')</span>';
        };

        $out = '<h1 style="color:#1d273b;">' . $e($title) . '</h1>';
        $out .= '<p>' . $e(sprintf(__('Entity: %s', 'erm'), \Dropdown::getDropdownName('glpi_entities', (int) ($_SESSION['glpiactive_entity'] ?? 0))))
            . '<br>' . $e(sprintf(__('Generated on %1$s by %2$s', 'erm'), \Html::convDateTime(date('Y-m-d H:i')), getUserName((int) Session::getLoginUserID())))
            . ($d['compare'] ? '<br>' . $e(sprintf(__('Compared with the snapshot of %s', 'erm'), Period::label($d['compare']))) : '<br>' . $e(__('No previous snapshot to compare with.', 'erm')))
            . '</p>';

        // 1. Executive summary
        $out .= $h2(__('1. Executive summary', 'erm'));
        $b = $k['before'];
        $out .= '<table cellpadding="4" border="1"><tr><td ' . $th . '>' . $e(__('Indicator', 'erm')) . '</td><td ' . $th . '>' . $e(__('Current', 'erm')) . '</td>'
            . ($b ? '<td ' . $th . '>' . $e(Period::label($d['compare'])) . '</td>' : '') . '</tr>';
        $rows = [[__('Active risks', 'erm'), $k['now']['count'], $b['count'] ?? null, false]];
        foreach (array_reverse(Scale::LEVELS) as $lvl) {
            $rows[] = [sprintf(__('Residual %s', 'erm'), mb_strtolower($levels[$lvl])), $k['now']['levels'][$lvl] ?? 0, $b ? ($b['levels'][$lvl] ?? 0) : null, false];
        }
        $rows[] = [__('Outside risk appetite', 'erm'), $k['now']['outside'], $b['outside'] ?? null, false];
        $rows[] = [__('Maximum economic exposure', 'erm'), $k['now']['exposure'], $b['exposure'] ?? null, true];
        foreach ($rows as [$label, $now, $before, $isMoney]) {
            $out .= '<tr><td>' . $e($label) . '</td><td>' . ($isMoney ? $money($now) : $e($now)) . $delta($isMoney ? (float) $now : (int) $now, $before === null ? null : ($isMoney ? (float) $before : (int) $before)) . '</td>'
                . ($b ? '<td>' . ($isMoney ? $money($before) : $e($before)) . '</td>' : '') . '</tr>';
        }
        $out .= '</table>';
        $tc = $d['treatment'];
        $out .= '<p>' . $e(sprintf(
            __('Treatment: %1$d at target, %2$d in progress, %3$d plans completed without reaching the target, %4$d above target without plan. Overdue actions: %5$d. KRIs in alert: %6$d.', 'erm'),
            $tc['reached'] ?? 0,
            $tc['in_progress'] ?? 0,
            $tc['ineffective'] ?? 0,
            $tc['no_plan'] ?? 0,
            count($d['overdue']),
            count($d['kris'])
        ));
        if ($d['net_loss'] !== null) {
            $out .= '<br>' . $e(sprintf(__('Net losses of the last 12 months: %1$s. Risks underestimated according to their loss history: %2$d.', 'erm'), Scale::money($d['net_loss']), $d['underestimated']));
        }
        $out .= '</p>';

        // 2. Changes since the previous snapshot
        if ($d['compare']) {
            $out .= $h2(sprintf(__('2. Changes since %s', 'erm'), Period::label($d['compare'])));
            $sections = ['up' => __('Risks that increased', 'erm'), 'down' => __('Risks that decreased', 'erm'), 'new' => __('New risks', 'erm'),
                         'into_outside' => __('Risks that went outside the appetite', 'erm'), 'back_within' => __('Risks back within the appetite', 'erm'),
                         'removed' => __('Closed or removed risks', 'erm')];
            foreach ($sections as $key => $label) {
                $list = $d['diff'][$key];
                $out .= '<p><b>' . $e($label) . ' (' . count($list) . ')</b>';
                foreach ($list as $r) {
                    $out .= '<br>- ' . $e($r['code'] . ' ' . $r['name']) . ' ' . (isset($r['previous_level']) ? $badge($r['previous_level']) . ' &gt; ' : '') . $badge(SnapshotDiff::level($r));
                }
                $out .= '</p>';
            }
        }

        // 3. Residual heat map
        $out .= $h2(__('3. Residual heat map', 'erm'));
        $out .= '<table cellpadding="5" border="1" style="text-align:center;"><tr><td ' . $th . '>' . $e(__('Probability', 'erm')) . ' / ' . $e(__('Impact', 'erm')) . '</td>';
        foreach (range(1, 5) as $i) {
            $out .= '<td ' . $th . '>' . $i . '</td>';
        }
        $out .= '</tr>';
        foreach (range(5, 1) as $p) {
            $out .= '<tr><td ' . $th . '>' . $p . '</td>';
            foreach (range(1, 5) as $i) {
                $lvl = Scale::levelFor($p, $i);
                $out .= sprintf('<td style="background-color:%s;color:%s;font-weight:bold;">%s</td>', Scale::color($lvl), $lvl === 'medium' ? '#1d273b' : '#ffffff', $e($d['heat'][$p][$i] ?? ''));
            }
            $out .= '</tr>';
        }
        $out .= '</table>';

        // 4. Outside appetite and 5. Top risks
        $list = function (array $rows, bool $position) use ($e, $badge, $th): string {
            $o = '<table cellpadding="3" border="1"><tr><td ' . $th . ' width="14%">' . $e(__('Code', 'erm')) . '</td><td ' . $th . ' width="46%">' . $e(__('Risk', 'erm'))
                . '</td><td ' . $th . ' width="20%">' . $e(__('Residual', 'erm')) . '</td><td ' . $th . ' width="20%">' . $e($position ? __('Position', 'erm') : __('Risk owner', 'erm')) . '</td></tr>';
            foreach ($rows as $r) {
                $o .= '<tr><td>' . $e($r['code']) . '</td><td>' . $e($r['name']) . '</td><td>' . $badge(DrillFilter::level($r)) . '</td><td>'
                    . $e($position ? $r['appetite'][1] : ($r['users_id'] ? getUserName((int) $r['users_id']) : '')) . '</td></tr>';
            }
            return $o . '</table>';
        };
        $out .= $h2(__('4. Risks outside the appetite', 'erm')) . ($d['outside'] ? $list($d['outside'], true) : '<p>' . $e(__('All residual risks are within appetite.', 'erm')) . '</p>');
        $out .= $h2(__('5. Top 10 residual risks', 'erm')) . $list($d['top'], false);

        // 6. Integrated view
        $int = $d['integrated'];
        if ($int['grc']) {
            $out .= $h2(__('6. Integrated view (corporate, information security and supplier risks)', 'erm'));
            $out .= '<table cellpadding="4" border="1"><tr><td ' . $th . '>' . $e(__('Source', 'erm')) . '</td>';
            foreach (array_reverse(Scale::LEVELS) as $lvl) {
                $out .= '<td ' . $th . '>' . $e($levels[$lvl]) . '</td>';
            }
            $out .= '<td ' . $th . '>' . $e(__('Total', 'erm')) . '</td></tr>';
            foreach ($int['sources'] as $key => $src) {
                $out .= '<tr><td>' . $e($src['label']) . '</td>';
                foreach (array_reverse(Scale::LEVELS) as $lvl) {
                    $out .= '<td>' . $e($int['counts'][$key][$lvl]) . '</td>';
                }
                $out .= '<td><b>' . $e($int['counts'][$key]['total']) . '</b></td></tr>';
            }
            $out .= '</table>';
            if ($int['incoherent']) {
                $out .= '<p><b>' . $e(__('Corporate risks assessed below their linked risks', 'erm')) . '</b>';
                foreach ($int['incoherent'] as $i) {
                    $out .= '<br>- ' . $e($i['risk']['code'] . ' ' . $i['risk']['name']) . ' ' . $badge($i['level']) . ' &lt; ' . $badge($i['highest']);
                }
                $out .= '</p>';
            }
        }
        return $out;
    }
}

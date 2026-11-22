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

use GLPIPDF;
use Session;

/**
 * Risk management methodology document (ISO 31000 6.3, ISO/IEC 27001 6.1.2) generated from the current configuration
 * of the active entity: scales and economic criteria, matrix, appetite and tolerance, roles, acceptance, monitoring
 * and reporting. Always up to date, it is also the training material for new users.
 */
final class Methodology
{
    public static function getURL(): string
    {
        return plugin_erm_url('front/methodology.php');
    }

    public static function pdf(): void
    {
        $title = __('Risk management methodology', 'erm');
        $pdf = new GLPIPDF(['orientation' => 'P', 'font_size' => 9], 1, $title);
        $pdf->SetTitle($title);
        $pdf->writeHTML(self::html(), true, false, true, false, '');
        $pdf->Output('erm-methodology-' . date('Y-m-d') . '.pdf', 'D');
    }

    /** HTML (TCPDF subset) of the document. All values are escaped. */
    public static function html(): string
    {
        global $DB;
        $e = fn($v) => htmlescape((string) $v);
        $h2 = fn(string $t) => '<h3 style="color:#1d273b;border-bottom:1px solid #999;">' . $e($t) . '</h3>';
        $p = fn(string $t) => '<p>' . $e($t) . '</p>';
        $ul = fn(array $items) => '<ul>' . implode('', array_map(fn($i) => '<li>' . $e($i) . '</li>', $items)) . '</ul>';
        $th = 'style="background-color:#e9ecef;font-weight:bold;"';
        $entityId = (int) ($_SESSION['glpiactive_entity'] ?? 0);
        $levels = Scale::levels();

        $out = '<h1 style="color:#1d273b;">' . $e(__('Risk management methodology', 'erm')) . '</h1>';
        $out .= '<p>' . $e(sprintf(__('Entity: %s', 'erm'), \Dropdown::getDropdownName('glpi_entities', $entityId))) . '<br>'
            . $e(sprintf(__('Generated on %1$s by %2$s from the configuration in force. Any change of the configuration updates this document.', 'erm'), \Html::convDateTime(date('Y-m-d H:i')), getUserName((int) Session::getLoginUserID())))
            . '</p>';

        $out .= $h2(__('1. Purpose and reference framework', 'erm'));
        $out .= $p(__('This methodology describes how the organization identifies, analyses, evaluates, treats, monitors and reports its corporate risks, following ISO 31000 and COSO ERM. Information security risks are managed with the same criteria in the ISMS (ISO/IEC 27001) and AI risks according to ISO/IEC 42001.', 'erm'));

        $out .= $h2(__('2. Process', 'erm'));
        $out .= $ul([
            __('Identification: each risk is written as an event, with its causes and consequences, a category and an owner.', 'erm'),
            __('Analysis: probability and impact are assessed without controls (inherent risk) and with the existing controls (residual risk).', 'erm'),
            __('Evaluation: the residual level is compared with the appetite and tolerance of its category.', 'erm'),
            __('Treatment: mitigate, transfer, avoid or accept; a target level and an action plan with responsibles and due dates.', 'erm'),
            __('Monitoring: key risk indicators, loss events, periodic reviews and the effectiveness of the treatment.', 'erm'),
            __('Recording and reporting: register in the tool, dashboard, integrated map and quarterly Board report.', 'erm'),
        ]);

        $out .= $h2(__('3. Probability scale', 'erm'));
        $rows = '';
        foreach (RiskWizard::probabilityAnswers() as $k => $plain) {
            $rows .= '<tr><td>' . $e(Scale::probabilities()[$k]) . '</td><td>' . $e($plain) . '</td></tr>';
        }
        $out .= '<table cellpadding="4" border="1"><tr><td ' . $th . ' width="35%">' . $e(__('Level', 'erm')) . '</td><td ' . $th . ' width="65%">'
            . $e(__('Meaning', 'erm')) . '</td></tr>' . $rows . '</table>';

        $out .= $h2(__('4. Impact scale and economic criteria', 'erm'));
        $rows = '';
        foreach (RiskWizard::impactAnswers($entityId) as $k => $plain) {
            $rows .= '<tr><td>' . $e(Scale::impacts()[$k]) . '</td><td>' . $e($plain) . '</td></tr>';
        }
        $out .= '<table cellpadding="4" border="1"><tr><td ' . $th . ' width="35%">' . $e(__('Level', 'erm')) . '</td><td ' . $th . ' width="65%">'
            . $e(__('Meaning', 'erm')) . '</td></tr>' . $rows . '</table>';
        $out .= $p(__('The worst consequence among money, customers, regulators, reputation and people determines the impact.', 'erm'));

        $out .= $h2(__('5. Risk matrix and levels', 'erm'));
        $out .= '<table cellpadding="5" border="1" style="text-align:center;"><tr><td ' . $th . '>' . $e(__('Probability', 'erm')) . ' / ' . $e(__('Impact', 'erm')) . '</td>';
        foreach (range(1, 5) as $i) {
            $out .= '<td ' . $th . '>' . $i . '</td>';
        }
        $out .= '</tr>';
        foreach (range(5, 1) as $pr) {
            $out .= '<tr><td ' . $th . '>' . $pr . '</td>';
            foreach (range(1, 5) as $i) {
                $lvl = Scale::levelFor($pr, $i);
                $out .= sprintf('<td style="background-color:%s;color:%s;">%d</td>', Scale::color($lvl), $lvl === 'medium' ? '#1d273b' : '#ffffff', $pr * $i);
            }
            $out .= '</tr>';
        }
        $out .= '</table>';
        $out .= $p(sprintf(__('Level = probability x impact: %1$s 1-4, %2$s 5-9, %3$s 10-16, %4$s 20-25.', 'erm'), $levels['low'], $levels['medium'], $levels['high'], $levels['critical']));

        $out .= $h2(__('6. Risk appetite and tolerance', 'erm'));
        $out .= $p(__('Appetite: maximum residual level the organization wishes to take in each category. Tolerance: maximum admissible level; above it, immediate action is required.', 'erm'));
        $out .= '<table cellpadding="4" border="1"><tr><td ' . $th . ' width="24%">' . $e(__('Category', 'erm')) . '</td><td ' . $th . ' width="12%">'
            . $e(__('Appetite', 'erm')) . '</td><td ' . $th . ' width="12%">' . $e(__('Tolerance', 'erm')) . '</td><td ' . $th . ' width="52%">'
            . $e(__('Appetite statement', 'erm')) . '</td></tr>';
        $overrides = EntityConfig::overrides();
        foreach ($DB->request(['FROM' => Category::getTable(), 'WHERE' => ['plugin_erm_categories_id' => 0], 'ORDER' => 'name']) as $c) {
            [$app, $tol] = Category::effectiveAppetite((int) $c['id'], $entityId);
            $statement = (string) $c['appetite_statement'];
            foreach (EntityConfig::entityChain($entityId) as $eid) {
                if (!empty($overrides[(int) $c['id']][$eid]['appetite_statement'])) {
                    $statement = $overrides[(int) $c['id']][$eid]['appetite_statement'];
                    break;
                }
            }
            $out .= '<tr><td>' . $e($c['name']) . '</td><td>' . $e($levels[$app] ?? '-') . '</td><td>' . $e($levels[$tol] ?? '-') . '</td><td>' . $e($statement) . '</td></tr>';
        }
        $out .= '</table>';

        $out .= $h2(__('7. Roles and responsibilities', 'erm'));
        $out .= '<table cellpadding="4" border="1">';
        foreach (Profile::roleProfiles() as $role) {
            $out .= '<tr><td width="30%"><b>' . $e($role['name']) . '</b></td><td width="70%">' . $e($role['comment']) . '</td></tr>';
        }
        $out .= '</table>';

        $out .= $h2(__('8. Treatment, acceptance and segregation of duties', 'erm'));
        $out .= $ul([
            __('Every risk to be mitigated or transferred has a treatment plan with responsibles and due dates, and a target level.', 'erm'),
            sprintf(__('A risk is accepted only by the Risk Committee or the Board, never by its owner, with a justification and for %d months; then it must be accepted again.', 'erm'), AcceptanceState::VALIDITY_MONTHS),
            __('AI impact assessments are approved by someone who did not write or edit them.', 'erm'),
            __('When all actions are closed, the residual risk is reassessed and compared with the target (treatment effectiveness).', 'erm'),
        ]);

        $out .= $h2(__('9. Monitoring and review', 'erm'));
        $out .= $ul([
            __('Every risk is reviewed at least once a year (high and critical risks more often); each review keeps a snapshot of the assessment.', 'erm'),
            __('Key risk indicators have warning and critical thresholds; automatic indicators are measured from GLPI saved searches.', 'erm'),
            sprintf(__('Loss events (losses, near misses, gains) are recorded and linked to the risks; the history of the last %d years suggests probability and impact.', 'erm'), LossStats::WINDOW_YEARS),
            __('Owners and responsibles receive e-mail alerts: assignments, actions due or overdue, reviews, KRIs, risks above appetite or tolerance, expiring acceptances.', 'erm'),
            __('Any person can report risks and incidents from the simplified interface; the risk team reviews every report.', 'erm'),
        ]);

        $out .= $h2(__('10. Reporting', 'erm'));
        $out .= $ul([
            __('Dashboard with heat maps, appetite, treatment effectiveness, loss history and AI governance.', 'erm'),
            __('Integrated map with the information security and supplier risks.', 'erm'),
            __('Quarterly snapshot and Board report comparing with the previous quarter.', 'erm'),
        ]);

        $out .= $h2(__('11. Quality criteria of the register', 'erm'));
        $out .= $ul(array_values(MethodChecks::messages()));
        return $out;
    }
}

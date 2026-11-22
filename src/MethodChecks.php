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

/**
 * Methodology quality checks of a risk (ISO 31000): non-blocking suggestions that a risk expert would make when
 * reading the register. They teach the methodology while the tool is used. Pure logic.
 */
final class MethodChecks
{
    /** Minimum number of words to describe a risk as an event ("Failure of supplier X that stops collections"). */
    public const MIN_NAME_WORDS = 4;

    /**
     * @param array<string,mixed> $risk     fields of the risk
     * @param int                 $actions  number of treatment actions
     * @param string              $position appetite position (Scale::appetitePosition()[0])
     * @return list<string> codes of the issues found (see messages())
     */
    public static function check(array $risk, int $actions, string $position, string $today): array
    {
        $issues = [];
        $name = trim((string) ($risk['name'] ?? ''));
        if ($name !== '' && count(preg_split('/\s+/u', $name) ?: []) < self::MIN_NAME_WORDS) {
            $issues[] = 'name_short';
        }
        if (trim((string) ($risk['causes'] ?? '')) === '') {
            $issues[] = 'no_causes';
        }
        if (trim((string) ($risk['consequences'] ?? '')) === '') {
            $issues[] = 'no_consequences';
        }
        if ((int) ($risk['users_id'] ?? 0) === 0) {
            $issues[] = 'no_owner';
        }
        $inherent = (int) ($risk['inherent_score'] ?? 0);
        $residual = (int) ($risk['residual_score'] ?? 0);
        $target = (int) ($risk['target_score'] ?? 0);
        if ($inherent === 0) {
            $issues[] = 'not_assessed';
        }
        if ($residual > 0 && $inherent > 0 && $residual > $inherent) {
            $issues[] = 'residual_above_inherent';
        }
        if ($residual > 0 && $residual < $inherent && trim((string) ($risk['existing_controls'] ?? '')) === '') {
            $issues[] = 'residual_without_controls';
        }
        if ($target > 0 && $residual > 0 && $target > $residual) {
            $issues[] = 'target_above_residual';
        }
        $treatment = (string) ($risk['treatment'] ?? '');
        if (in_array($treatment, ['mitigate', 'transfer'], true) && $actions === 0) {
            $issues[] = 'treatment_without_actions';
        }
        if ($treatment === 'accept' && trim((string) ($risk['justification'] ?? '')) === '') {
            $issues[] = 'accept_without_justification';
        }
        if (in_array($position, ['above_appetite', 'above_tolerance'], true) && $treatment === '') {
            $issues[] = 'outside_without_treatment';
        }
        $review = (string) ($risk['review_date'] ?? '');
        if ($review === '') {
            $issues[] = 'no_review_date';
        } elseif ($review < $today) {
            $issues[] = 'review_overdue';
        }
        return $issues;
    }

    /** Plain-language explanation of each issue, with what to do. */
    public static function messages(): array
    {
        return [
            'name_short'      => __('Describe the risk as an event, not as a topic: what could happen and to what. Example: "Failure of the core banking provider that stops collections for more than a day" instead of "Providers".', 'erm'),
            'no_causes'       => __('Add the causes: why could it happen? Causes show which controls are needed.', 'erm'),
            'no_consequences' => __('Add the consequences: what would happen to customers, money, reputation or compliance? They justify the impact.', 'erm'),
            'no_owner'        => __('Assign a risk owner: the person accountable for managing it (usually the manager of the process).', 'erm'),
            'not_assessed'    => __('Assess the inherent risk (probability and impact without controls) so that it appears in the heat map.', 'erm'),
            'residual_above_inherent' => __('The residual risk is higher than the inherent one: controls cannot increase a risk. Review both assessments.', 'erm'),
            'residual_without_controls' => __('The residual risk is lower than the inherent one but no existing control is described: explain which controls reduce it.', 'erm'),
            'target_above_residual' => __('The target is worse than the current residual risk: the target is the level the treatment has to reach.', 'erm'),
            'treatment_without_actions' => __('The decision is to mitigate or transfer but there is no action in the treatment plan: add at least one action with a responsible and a due date.', 'erm'),
            'accept_without_justification' => __('A risk is only accepted with a justification: explain why it is reasonable to accept it (cost, low exposure, temporary situation...).', 'erm'),
            'outside_without_treatment' => __('The residual risk is outside the appetite and there is no treatment decision: decide whether to mitigate, transfer, avoid or formally accept it.', 'erm'),
            'no_review_date'  => __('Set the next review date: every risk is reviewed at least once a year.', 'erm'),
            'review_overdue'  => __('The review date has passed: review the risk and record the review.', 'erm'),
        ];
    }
}

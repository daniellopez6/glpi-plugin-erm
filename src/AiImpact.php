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
 * Rules of the AI system impact assessment (ISO/IEC 42001 6.1.4 and 8.4, criteria of ISO/IEC 42005) and of the
 * AI governance checks. Pure logic.
 */
final class AiImpact
{
    /** Assessed dimensions (score 1-5 each, 0 = not assessed). */
    public const DIMENSIONS = ['fairness', 'privacy', 'transparency', 'safety', 'reliability', 'oversight', 'misuse', 'societal', 'legal'];

    /** AI Act classes that require an approved impact assessment before production. */
    public const ASSESSMENT_REQUIRED = ['high', 'limited'];

    public static function dimensions(): array
    {
        return [
            'fairness'     => __('Bias and discrimination', 'erm'),
            'privacy'      => __('Privacy and personal data', 'erm'),
            'transparency' => __('Transparency and explainability', 'erm'),
            'safety'       => __('Safety and security (attacks, prompt injection)', 'erm'),
            'reliability'  => __('Reliability, accuracy and hallucinations', 'erm'),
            'oversight'    => __('Human oversight and autonomy', 'erm'),
            'misuse'       => __('Misuse and unintended use', 'erm'),
            'societal'     => __('Impact on people, society and environment', 'erm'),
            'legal'        => __('Legal and contractual compliance', 'erm'),
        ];
    }

    public static function scores(): array
    {
        return [
            0 => '-',
            1 => __('1 - Negligible', 'erm'),
            2 => __('2 - Limited', 'erm'),
            3 => __('3 - Moderate', 'erm'),
            4 => __('4 - Significant', 'erm'),
            5 => __('5 - Severe', 'erm'),
        ];
    }

    /**
     * Overall impact level: driven by the worst dimension (an AI system is as harmful as its worst failure mode);
     * three or more significant dimensions raise a high result to critical.
     *
     * @param array<string,int|string|null> $scores dimension => score
     */
    public static function overall(array $scores): string
    {
        $values = array_map('intval', array_intersect_key($scores, array_flip(self::DIMENSIONS)));
        $values = array_filter($values, fn($v) => $v >= 1 && $v <= 5);
        if (!$values) {
            return '';
        }
        $max = max($values);
        $level = match (true) {
            $max >= 5 => 'critical',
            $max === 4 => 'high',
            $max === 3 => 'medium',
            default => 'low',
        };
        if ($level === 'high' && count(array_filter($values, fn($v) => $v >= 4)) >= 3) {
            $level = 'critical';
        }
        return $level;
    }

    /** Dimension with the highest score ('' when none). */
    public static function worstDimension(array $scores): string
    {
        $values = array_filter(array_map('intval', array_intersect_key($scores, array_flip(self::DIMENSIONS))), fn($v) => $v > 0);
        if (!$values) {
            return '';
        }
        arsort($values);
        return (string) array_key_first($values);
    }

    /**
     * Governance gap of an AI system: in production (or development) with an AI Act class requiring an assessment
     * but without an approved and current one.
     *
     * @return string none | missing | expired | prohibited
     */
    public static function gap(string $status, string $aiActClass, ?string $lastApprovedNext, string $today, bool $hasApproved): string
    {
        if ($aiActClass === 'prohibited' && $status !== 'retired') {
            return 'prohibited';
        }
        if (!in_array($status, ['development', 'production'], true) || !in_array($aiActClass, self::ASSESSMENT_REQUIRED, true)) {
            return 'none';
        }
        if (!$hasApproved) {
            return 'missing';
        }
        return ($lastApprovedNext !== null && $lastApprovedNext !== '' && $lastApprovedNext < $today) ? 'expired' : 'none';
    }

    /** The approver must be someone else than the assessor (segregation of duties). */
    public static function canApprove(int $assessor, int $approver): bool
    {
        return $approver > 0 && $approver !== $assessor;
    }
}

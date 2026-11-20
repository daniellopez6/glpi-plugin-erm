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
 * 5x5 assessment scales (ISO 31000), levels, colours and risk appetite helpers.
 */
final class Scale
{
    public const LEVELS = ['low', 'medium', 'high', 'critical'];

    /**
     * Probability scale.
     *
     * @return array<int,string>
     */
    public static function probabilities(): array
    {
        return [
           1 => __('1 - Rare (< 5% per year)', 'erm'),
           2 => __('2 - Unlikely (5-25%)', 'erm'),
           3 => __('3 - Possible (25-50%)', 'erm'),
           4 => __('4 - Likely (50-80%)', 'erm'),
           5 => __('5 - Almost certain (> 80%)', 'erm'),
        ];
    }

    /**
     * Impact scale.
     *
     * @return array<int,string>
     */
    public static function impacts(): array
    {
        return [
           1 => __('1 - Insignificant', 'erm'),
           2 => __('2 - Minor', 'erm'),
           3 => __('3 - Moderate', 'erm'),
           4 => __('4 - Major', 'erm'),
           5 => __('5 - Catastrophic', 'erm'),
        ];
    }

    /**
     * Risk levels.
     *
     * @return array<string,string>
     */
    public static function levels(): array
    {
        return [
           'low'      => __('Low', 'erm'),
           'medium'   => __('Medium', 'erm'),
           'high'     => __('High', 'erm'),
           'critical' => __('Critical', 'erm'),
        ];
    }

    /**
     * Level for a probability x impact pair: 1-4 low, 5-9 medium, 10-16 high, 20-25 critical.
     */
    public static function levelFor(int $probability, int $impact): string
    {
        if ($probability < 1 || $impact < 1) {
            return '';
        }
        $score = $probability * $impact;
        return match (true) {
            $score <= 4  => 'low',
            $score <= 9  => 'medium',
            $score <= 16 => 'high',
            default      => 'critical',
        };
    }

    /**
     * Ordinal of a level (-1 when unknown).
     */
    public static function rank(?string $level): int
    {
        $i = array_search((string) $level, self::LEVELS, true);
        return $i === false ? -1 : $i;
    }

    /**
     * Display colour of a level.
     */
    public static function color(?string $level): string
    {
        return match ($level) {
            'low'      => '#2fb344',
            'medium'   => '#f5c518',
            'high'     => '#f76707',
            'critical' => '#d63939',
            default    => '#adb5bd',
        };
    }

    /**
     * HTML badge for a level (label is escaped).
     */
    public static function badge(?string $level, ?int $score = null): string
    {
        if (!$level) {
            return '<span class="badge bg-secondary-lt">' . htmlescape(__('Not assessed', 'erm')) . '</span>';
        }
        $label = self::levels()[$level] ?? $level;
        $fg    = $level === 'medium' ? '#1d273b' : '#fff';
        return sprintf(
            '<span class="badge" style="background:%s;color:%s">%s%s</span>',
            self::color($level),
            $fg,
            htmlescape($label),
            $score ? ' (' . $score . ')' : ''
        );
    }

    /**
     * Treatment decisions.
     *
     * @return array<string,string>
     */
    public static function treatments(): array
    {
        return [
           ''         => __('No decision', 'erm'),
           'mitigate' => __('Mitigate', 'erm'),
           'transfer' => __('Transfer', 'erm'),
           'avoid'    => __('Avoid', 'erm'),
           'accept'   => __('Accept', 'erm'),
        ];
    }

    /**
     * Risk statuses.
     *
     * @return array<string,string>
     */
    public static function statuses(): array
    {
        return [
           'identified'   => __('Identified', 'erm'),
           'assessed'     => __('Assessed', 'erm'),
           'in_treatment' => __('In treatment', 'erm'),
           'accepted'     => __('Accepted', 'erm'),
           'closed'       => __('Closed', 'erm'),
        ];
    }

    /**
     * Control effectiveness values.
     *
     * @return array<string,string>
     */
    public static function effectiveness(): array
    {
        return [
           ''       => '-',
           'none'   => __('None', 'erm'),
           'low'    => __('Low', 'erm'),
           'medium' => __('Medium', 'erm'),
           'high'   => __('High', 'erm'),
        ];
    }

    /**
     * Velocity (speed of onset) values.
     *
     * @return array<string,string>
     */
    public static function velocities(): array
    {
        return [
           ''          => '-',
           'immediate' => __('Immediate (days)', 'erm'),
           'short'     => __('Short (weeks)', 'erm'),
           'medium'    => __('Medium (months)', 'erm'),
           'long'      => __('Long (more than a year)', 'erm'),
        ];
    }

    /**
     * Trend values.
     *
     * @return array<string,string>
     */
    public static function trends(): array
    {
        return [
           ''       => '-',
           'up'     => __('Increasing', 'erm'),
           'stable' => __('Stable', 'erm'),
           'down'   => __('Decreasing', 'erm'),
        ];
    }

    /**
     * Treatment action statuses.
     *
     * @return array<string,string>
     */
    public static function actionStatuses(): array
    {
        return [
           'planned'     => __('Planned', 'erm'),
           'in_progress' => __('In progress', 'erm'),
           'done'        => __('Done', 'erm'),
           'cancelled'   => __('Cancelled', 'erm'),
        ];
    }

    /**
     * Position of a residual level against the appetite/tolerance of its category.
     *
     * @return array{0:string,1:string} [key, label]
     */
    public static function appetitePosition(?string $level, ?string $appetite, ?string $tolerance): array
    {
        if (!$level || (!$appetite && !$tolerance)) {
            return ['undefined', __('No appetite defined', 'erm')];
        }
        if ($tolerance && self::rank($level) > self::rank($tolerance)) {
            return ['above_tolerance', __('Above tolerance', 'erm')];
        }
        if ($appetite && self::rank($level) > self::rank($appetite)) {
            return ['above_appetite', __('Above appetite', 'erm')];
        }
        return ['within', __('Within appetite', 'erm')];
    }

    /**
     * HTML badge for an appetite position.
     *
     * @param array{0:string,1:string} $pos
     */
    public static function appetiteBadge(array $pos): string
    {
        $class = [
           'within'          => 'bg-green-lt',
           'above_appetite'  => 'bg-orange-lt',
           'above_tolerance' => 'bg-red-lt',
        ][$pos[0]] ?? 'bg-secondary-lt';
        return sprintf('<span class="badge %s">%s</span>', $class, htmlescape($pos[1]));
    }

    /**
     * Amount formatted with the user number format (no currency symbol: amounts are in the organisation currency).
     */
    public static function money(?float $value): string
    {
        if ($value === null) {
            return '-';
        }
        return \Html::formatNumber($value, false, 0);
    }
}

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
 * Rules of the integrated risk map (corporate risks + ISMS and supplier risks of GLPI GRC Manager). Pure logic.
 *
 * Both plugins use the same level names (low, medium, high, critical), so risks are consolidated by level.
 */
final class Consolidation
{
    /** Level of a GRC Manager risk: residual when assessed, otherwise the inherent level. */
    public static function grcLevel(array $risk): string
    {
        foreach (['residual_level', 'risk_level'] as $field) {
            $level = (string) ($risk[$field] ?? '');
            if (in_array($level, Scale::LEVELS, true)) {
                return $level;
            }
        }
        return '';
    }

    /**
     * Highest level among linked risks, or '' when none.
     *
     * @param list<string> $levels
     */
    public static function highest(array $levels): string
    {
        $best = '';
        foreach ($levels as $level) {
            if (Scale::rank($level) > Scale::rank($best)) {
                $best = $level;
            }
        }
        return $best;
    }

    /**
     * A corporate risk is assessed below its linked ISMS / supplier risks when its level is lower than the highest
     * of them: the aggregated (corporate) view should not be lighter than its components.
     *
     * @param list<string> $linkedLevels
     */
    public static function belowLinked(string $corporateLevel, array $linkedLevels): bool
    {
        $highest = self::highest($linkedLevels);
        return $highest !== '' && Scale::rank($highest) > Scale::rank($corporateLevel);
    }
}

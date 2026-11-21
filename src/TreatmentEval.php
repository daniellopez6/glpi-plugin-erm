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
 * Effectiveness of the treatment of a risk (ISO 31000 6.5.3): progress of the plan and residual vs. target level.
 * Pure logic (no database) so it can be unit tested.
 */
final class TreatmentEval
{
    /**
     * @return string no_target | reached | no_plan | in_progress | ineffective
     */
    public static function evaluate(int $total, int $closed, int $residualScore, int $targetScore): string
    {
        if ($targetScore <= 0) {
            return 'no_target';
        }
        if ($residualScore > 0 && $residualScore <= $targetScore) {
            return 'reached';
        }
        if ($total === 0) {
            return 'no_plan';
        }
        return $closed < $total ? 'in_progress' : 'ineffective';
    }

    /** Percentage of actions done or cancelled. */
    public static function progress(int $total, int $closed): int
    {
        return $total > 0 ? (int) round(100 * min($closed, $total) / $total) : 0;
    }
}

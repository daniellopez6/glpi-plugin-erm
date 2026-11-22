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
 * Translates the plain-language answers of the risk wizard into the methodology fields. Pure logic.
 */
final class WizardMapper
{
    /** "Do the controls work?" -> control effectiveness. */
    public static function effectiveness(string $answer): string
    {
        return match ($answer) {
            'tested'  => 'high',
            'believe' => 'medium',
            'partial' => 'low',
            'none'    => 'none',
            default   => '',
        };
    }

    /**
     * Residual value: "same as without controls" (or no controls) keeps the inherent value; it can never exceed it.
     */
    public static function residual(string $choice, int $inherent, string $controls): int
    {
        if ($choice === 'same' || $choice === '' || $controls === 'none') {
            return $inherent;
        }
        $value = max(0, min(5, (int) $choice));
        return $inherent > 0 ? min($value, $inherent) : $value;
    }

    /** Initial status of the risk created by the wizard. */
    public static function status(int $probability, int $impact, string $treatment): string
    {
        if ($probability < 1 || $impact < 1) {
            return 'identified';
        }
        return in_array($treatment, ['mitigate', 'transfer', 'avoid'], true) ? 'in_treatment' : 'assessed';
    }
}

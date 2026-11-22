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
 * "Getting started" checklist of the ERM implementation, computed from facts of the installation. Pure logic.
 */
final class Onboarding
{
    /** Minimum number of risks of a first register. */
    public const MIN_RISKS = 5;

    /** Share of active risks that must be assessed (inherent and residual). */
    public const MIN_ASSESSED = 0.8;

    /**
     * @param array<string,int|bool> $facts impact_criteria, appetite_categories, root_categories, role_profiles_assigned,
     *                                     risks, assessed_risks, kris, reviews, snapshots, notifications
     * @return list<array{key:string, done:bool}>
     */
    public static function steps(array $facts): array
    {
        $risks = (int) ($facts['risks'] ?? 0);
        $done = [
            'impact_criteria' => !empty($facts['impact_criteria']),
            'appetite'        => (int) ($facts['root_categories'] ?? 0) > 0
                && (int) ($facts['appetite_categories'] ?? 0) >= (int) $facts['root_categories'],
            'profiles'        => !empty($facts['role_profiles_assigned']),
            'risks'           => $risks >= self::MIN_RISKS,
            'assessed'        => $risks > 0 && (int) ($facts['assessed_risks'] ?? 0) >= (int) ceil($risks * self::MIN_ASSESSED),
            'kris'            => (int) ($facts['kris'] ?? 0) > 0,
            'reviews'         => (int) ($facts['reviews'] ?? 0) > 0,
            'snapshot'        => (int) ($facts['snapshots'] ?? 0) > 0,
            'notifications'   => !empty($facts['notifications']),
        ];
        return array_map(fn($k) => ['key' => $k, 'done' => $done[$k]], array_keys($done));
    }

    /**
     * @param list<array{key:string, done:bool}> $steps
     */
    public static function progress(array $steps): int
    {
        return $steps ? (int) round(100 * count(array_filter($steps, fn($s) => $s['done'])) / count($steps)) : 100;
    }
}

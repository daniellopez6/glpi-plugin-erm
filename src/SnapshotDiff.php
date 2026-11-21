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
 * Changes of the risk profile between two snapshots (previous quarter vs. current). Pure logic.
 */
final class SnapshotDiff
{
    /**
     * @param array<int, array<string,mixed>> $before risks of the previous snapshot, indexed by risk id
     * @param array<int, array<string,mixed>> $after  current risks (or a later snapshot), indexed by risk id
     * @return array{new: list<array<string,mixed>>, removed: list<array<string,mixed>>, up: list<array<string,mixed>>,
     *               down: list<array<string,mixed>>, into_outside: list<array<string,mixed>>, back_within: list<array<string,mixed>>}
     */
    public static function compare(array $before, array $after): array
    {
        $out = ['new' => [], 'removed' => [], 'up' => [], 'down' => [], 'into_outside' => [], 'back_within' => []];
        foreach ($after as $id => $r) {
            if (!isset($before[$id])) {
                $out['new'][] = $r;
                continue;
            }
            $prev = $before[$id];
            $delta = Scale::rank(self::level($r)) - Scale::rank(self::level($prev));
            if ($delta > 0) {
                $out['up'][] = $r + ['previous_level' => self::level($prev)];
            } elseif ($delta < 0) {
                $out['down'][] = $r + ['previous_level' => self::level($prev)];
            }
            $wasOut = self::isOutside($prev['appetite_position'] ?? '');
            $isOut = self::isOutside($r['appetite_position'] ?? '');
            if (!$wasOut && $isOut) {
                $out['into_outside'][] = $r;
            } elseif ($wasOut && !$isOut) {
                $out['back_within'][] = $r;
            }
        }
        foreach ($before as $id => $r) {
            if (!isset($after[$id])) {
                $out['removed'][] = $r;
            }
        }
        return $out;
    }

    /** Effective level of a snapshot row (residual, inherent when not assessed). */
    public static function level(array $r): string
    {
        return (string) (($r['residual_level'] ?? '') ?: ($r['inherent_level'] ?? ''));
    }

    private static function isOutside(string $position): bool
    {
        return in_array($position, ['above_appetite', 'above_tolerance'], true);
    }
}

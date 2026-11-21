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
 * Reporting periods (quarters) of the risk snapshots. Pure logic.
 */
final class Period
{
    /** Quarter of a date: 2026-11-21 -> 2026-Q4. */
    public static function quarterOf(string $date): string
    {
        $y = (int) substr($date, 0, 4);
        $m = (int) substr($date, 5, 2);
        return sprintf('%04d-Q%d', $y, intdiv(max(1, min(12, $m)) - 1, 3) + 1);
    }

    public static function previous(string $period): string
    {
        [$y, $q] = self::parse($period);
        return $q === 1 ? sprintf('%04d-Q4', $y - 1) : sprintf('%04d-Q%d', $y, $q - 1);
    }

    public static function isValid(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-Q[1-4]$/', $period);
    }

    /** Human label: 2026-Q4 -> "Q4 2026". */
    public static function label(string $period): string
    {
        [$y, $q] = self::parse($period);
        return sprintf('Q%d %d', $q, $y);
    }

    /**
     * @return array{0:int, 1:int}
     */
    private static function parse(string $period): array
    {
        if (!self::isValid($period)) {
            throw new \InvalidArgumentException('Invalid period ' . $period);
        }
        return [(int) substr($period, 0, 4), (int) substr($period, 6, 1)];
    }
}

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
 * Filters applied when drilling down from a dashboard figure to the list of risks behind it.
 * Pure logic (no database) so it can be unit tested.
 */
final class DrillFilter
{
    /** Query parameters understood by the drill-down list. */
    public const KEYS = ['level', 'heat', 'p', 'i', 'category', 'entity', 'outside', 'overdue', 'kri', 'history', 'treatment'];

    /** Filters that select risks from a precomputed list of ids. */
    public const ID_FILTERS = ['overdue', 'kri', 'history', 'treatment'];

    /**
     * Keeps only the known, well-formed parameters.
     *
     * @param array<string,mixed> $query
     * @return array<string,string|int>
     */
    public static function fromQuery(array $query): array
    {
        $f = [];
        if (in_array($query['level'] ?? '', Scale::LEVELS, true)) {
            $f['level'] = $query['level'];
        }
        if (in_array($query['heat'] ?? '', ['inherent', 'residual'], true)) {
            $p = (int) ($query['p'] ?? 0);
            $i = (int) ($query['i'] ?? 0);
            if ($p >= 1 && $p <= 5 && $i >= 1 && $i <= 5) {
                $f += ['heat' => $query['heat'], 'p' => $p, 'i' => $i];
            }
        }
        if (is_string($query['category'] ?? null) && $query['category'] !== '') {
            $f['category'] = mb_substr($query['category'], 0, 255);
        }
        if (isset($query['entity']) && is_numeric($query['entity']) && (int) $query['entity'] >= 0) {
            $f['entity'] = (int) $query['entity'];
        }
        if (in_array($query['outside'] ?? '', ['any', 'above_appetite', 'above_tolerance'], true)) {
            $f['outside'] = $query['outside'];
        }
        foreach (self::ID_FILTERS as $flag) {
            if (!empty($query[$flag])) {
                $f[$flag] = 1;
            }
        }
        return $f;
    }

    /** Effective level of a risk: residual when assessed, inherent otherwise (same rule as the dashboard). */
    public static function level(array $risk): string
    {
        return (string) (($risk['residual_level'] ?? '') ?: ($risk['inherent_level'] ?? ''));
    }

    /** Root category name of a risk ("Financial > Credit" -> "Financial"). */
    public static function rootCategory(array $risk): string
    {
        return explode(' > ', (string) ($risk['category'] ?? ''))[0];
    }

    /**
     * @param list<array<string,mixed>> $risks       risks as returned by Risk::activeRisks()
     * @param array<string,string|int>  $f           filters from fromQuery()
     * @param array<string, list<int>>  $idSets      for each id filter (overdue, kri, history, treatment), the matching risk ids
     * @return list<array<string,mixed>>
     */
    public static function apply(array $risks, array $f, array $idSets = []): array
    {
        return array_values(array_filter($risks, static function (array $r) use ($f, $idSets): bool {
            if (isset($f['level']) && self::level($r) !== $f['level']) {
                return false;
            }
            if (isset($f['heat'])) {
                $k = $f['heat'];
                if ((int) $r["{$k}_probability"] !== $f['p'] || (int) $r["{$k}_impact"] !== $f['i']) {
                    return false;
                }
            }
            if (isset($f['category']) && self::rootCategory($r) !== $f['category']) {
                return false;
            }
            if (isset($f['entity']) && (int) $r['entities_id'] !== $f['entity']) {
                return false;
            }
            if (isset($f['outside'])) {
                $pos = $r['appetite'][0] ?? '';
                $ok = $f['outside'] === 'any' ? in_array($pos, ['above_appetite', 'above_tolerance'], true) : $pos === $f['outside'];
                if (!$ok) {
                    return false;
                }
            }
            foreach (self::ID_FILTERS as $flag) {
                if (isset($f[$flag]) && !in_array((int) $r['id'], $idSets[$flag] ?? [], true)) {
                    return false;
                }
            }
            return true;
        }));
    }
}

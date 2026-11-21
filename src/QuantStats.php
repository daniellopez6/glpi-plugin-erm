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
 * Quantitative risk analysis (ISO 31000 6.4.3 / ISO 31010): expected annual loss and Monte Carlo simulation of the
 * annual loss from the loss event history (Poisson frequency, severity resampled from the observed net losses).
 * Pure logic; the simulation is reproducible with a seed.
 */
final class QuantStats
{
    /** Middle of each probability band of the 1-5 scale. */
    private const PROBABILITY_MIDPOINTS = [1 => 0.025, 2 => 0.15, 3 => 0.375, 4 => 0.65, 5 => 0.90];

    /**
     * Expected annual loss from the assessment: probability band midpoint x middle of the economic impact range.
     */
    public static function assessedAle(int $probability, ?float $impactMin, ?float $impactMax): ?float
    {
        if (!isset(self::PROBABILITY_MIDPOINTS[$probability]) || ($impactMin === null && $impactMax === null)) {
            return null;
        }
        $impact = $impactMin !== null && $impactMax !== null ? ($impactMin + $impactMax) / 2 : (float) ($impactMax ?? $impactMin);
        return self::PROBABILITY_MIDPOINTS[$probability] * $impact;
    }

    /**
     * Expected annual loss from history: annual frequency x average net loss.
     *
     * @param list<float> $losses observed net losses
     */
    public static function historicalAle(float $frequency, array $losses): ?float
    {
        $losses = array_values(array_filter(array_map('floatval', $losses), fn($v) => $v > 0));
        if ($frequency <= 0 || !$losses) {
            return null;
        }
        return $frequency * array_sum($losses) / count($losses);
    }

    /**
     * Monte Carlo simulation of the annual loss.
     *
     * @param list<float> $losses observed net losses (severity distribution, resampled)
     * @return array{mean:float, p50:float, p90:float, p99:float, prob_loss:float, years:int}|null
     */
    public static function simulate(float $frequency, array $losses, int $years = 10000, int $seed = 42): ?array
    {
        $losses = array_values(array_filter(array_map('floatval', $losses), fn($v) => $v > 0));
        if ($frequency <= 0 || !$losses || $years < 100) {
            return null;
        }
        mt_srand($seed);
        $n = count($losses);
        $limit = exp(-$frequency);
        $totals = [];
        $withLoss = 0;
        for ($y = 0; $y < $years; $y++) {
            // Poisson number of events (Knuth)
            $k = 0;
            $p = 1.0;
            do {
                $k++;
                $p *= mt_rand() / mt_getrandmax();
            } while ($p > $limit);
            $events = $k - 1;
            $total = 0.0;
            for ($e = 0; $e < $events; $e++) {
                $total += $losses[mt_rand(0, $n - 1)];
            }
            $withLoss += $events > 0 ? 1 : 0;
            $totals[] = $total;
        }
        mt_srand();
        sort($totals);
        $pct = fn(float $q) => $totals[min($years - 1, (int) floor($q * $years))];
        return ['mean' => array_sum($totals) / $years, 'p50' => $pct(0.50), 'p90' => $pct(0.90), 'p99' => $pct(0.99),
                'prob_loss' => $withLoss / $years, 'years' => $years];
    }
}

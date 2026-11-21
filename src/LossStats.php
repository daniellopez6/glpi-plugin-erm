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

use DateTimeImmutable;

/**
 * Loss event statistics used to back the risk assessment with history (ISO 31000 6.4.3).
 * Pure logic (no database) so it can be unit tested.
 *
 * - Annual frequency (lambda) = loss events in the observation window / years of the window.
 * - Annual probability of at least one event = 1 - e^-lambda (Poisson), mapped to the 1-5 probability scale.
 * - Suggested impact = level whose minimum amount (entity criteria) is reached by the largest net loss.
 */
final class LossStats
{
    public const WINDOW_YEARS = 3;

    /** Upper bound (exclusive) of each probability level, as on the scale: <5%, 5-25%, 25-50%, 50-80%, >80%. */
    private const PROBABILITY_BOUNDS = [1 => 0.05, 2 => 0.25, 3 => 0.50, 4 => 0.80];

    public static function probabilityFromFrequency(float $lambda): float
    {
        return $lambda <= 0 ? 0.0 : 1 - exp(-$lambda);
    }

    public static function probabilityLevel(float $probability): int
    {
        foreach (self::PROBABILITY_BOUNDS as $level => $bound) {
            if ($probability < $bound) {
                return $level;
            }
        }
        return 5;
    }

    /**
     * Impact level reached by an amount.
     *
     * @param array<int,float|null> $thresholds minimum amount of levels 2..5 (null = not defined)
     */
    public static function impactLevel(float $amount, array $thresholds): ?int
    {
        $defined = array_filter($thresholds, fn($v) => $v !== null);
        if (!$defined || $amount <= 0) {
            return null;
        }
        $level = 1;
        foreach ($defined as $lvl => $min) {
            if ($amount >= (float) $min && $lvl > $level) {
                $level = (int) $lvl;
            }
        }
        return $level;
    }

    /**
     * @param list<array{event_type:string, occurrence_date:?string, net_loss:float|string|null}> $events
     * @param array<int,float|null> $thresholds
     * @return array{losses:int, near_misses:int, gains:int, frequency:float, probability:float, prob_level:?int,
     *               max_loss:float, total_loss:float, impact_level:?int, since:string}
     */
    public static function summarize(array $events, string $today, array $thresholds = [], int $years = self::WINDOW_YEARS): array
    {
        $since = (new DateTimeImmutable($today))->modify("-{$years} years")->format('Y-m-d');
        $out = ['losses' => 0, 'near_misses' => 0, 'gains' => 0, 'frequency' => 0.0, 'probability' => 0.0, 'prob_level' => null,
                'max_loss' => 0.0, 'total_loss' => 0.0, 'impact_level' => null, 'since' => $since];
        foreach ($events as $e) {
            $date = (string) ($e['occurrence_date'] ?? '');
            if ($date === '' || $date <= $since || $date > $today) {
                continue;
            }
            $net = (float) ($e['net_loss'] ?? 0);
            match ($e['event_type']) {
                'near_miss' => $out['near_misses']++,
                'gain'      => $out['gains']++,
                default     => $out['losses']++,
            };
            if ($e['event_type'] === 'loss') {
                $out['total_loss'] += $net;
                $out['max_loss'] = max($out['max_loss'], $net);
            }
        }
        if ($out['losses'] > 0) {
            $out['frequency'] = $out['losses'] / $years;
            $out['probability'] = self::probabilityFromFrequency($out['frequency']);
            $out['prob_level'] = self::probabilityLevel($out['probability']);
            $out['impact_level'] = self::impactLevel($out['max_loss'], $thresholds);
        }
        return $out;
    }

    /**
     * Compares an assessed level (1-5, 0 = not assessed) with the level suggested by history.
     *
     * @return string no_data | consistent | underestimated | overestimated
     */
    public static function compare(int $assessed, ?int $suggested): string
    {
        if ($suggested === null || $assessed < 1) {
            return 'no_data';
        }
        return match (true) {
            $suggested > $assessed => 'underestimated',
            $suggested < $assessed => 'overestimated',
            default                => 'consistent',
        };
    }
}

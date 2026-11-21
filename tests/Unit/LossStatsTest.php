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

namespace GlpiPlugin\Erm\Tests\Unit;

use GlpiPlugin\Erm\LossStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LossStatsTest extends TestCase
{
    private const THRESHOLDS = [2 => 1000000.0, 3 => 10000000.0, 4 => 50000000.0, 5 => 200000000.0];

    /**
     * @return array<string, array{0:float, 1:int}>
     */
    public static function frequencies(): array
    {
        // lambda (events per year) -> probability level
        return [
            'one in 30 years'  => [1 / 30, 1],
            'one in 3 years'   => [1 / 3, 3],
            'two in 3 years'   => [2 / 3, 3],
            'one per year'     => [1.0, 4],
            'two per year'     => [2.0, 5],
            'one in 10 years'  => [0.1, 2],
        ];
    }

    #[DataProvider('frequencies')]
    public function testProbabilityLevelFromFrequency(float $lambda, int $level): void
    {
        $this->assertSame($level, LossStats::probabilityLevel(LossStats::probabilityFromFrequency($lambda)));
    }

    public function testNoEventsMeansNoProbability(): void
    {
        $this->assertSame(0.0, LossStats::probabilityFromFrequency(0));
    }

    public function testImpactLevel(): void
    {
        $this->assertSame(1, LossStats::impactLevel(500000, self::THRESHOLDS));
        $this->assertSame(3, LossStats::impactLevel(10000000, self::THRESHOLDS));
        $this->assertSame(5, LossStats::impactLevel(900000000, self::THRESHOLDS));
        $this->assertNull(LossStats::impactLevel(900000000, []));
        $this->assertNull(LossStats::impactLevel(0, self::THRESHOLDS));
        $this->assertSame(4, LossStats::impactLevel(60000000, [2 => null, 3 => null, 4 => 50000000.0, 5 => null]));
    }

    public function testSummarizeCountsOnlyTheWindow(): void
    {
        $events = [
            ['event_type' => 'loss', 'occurrence_date' => '2026-03-10', 'net_loss' => 12000000],
            ['event_type' => 'loss', 'occurrence_date' => '2025-01-05', 'net_loss' => '3000000.50'],
            ['event_type' => 'loss', 'occurrence_date' => '2022-06-01', 'net_loss' => 900000000],   // outside the window
            ['event_type' => 'loss', 'occurrence_date' => '2027-01-01', 'net_loss' => 900000000],   // future date
            ['event_type' => 'near_miss', 'occurrence_date' => '2026-05-01', 'net_loss' => 0],
            ['event_type' => 'gain', 'occurrence_date' => '2026-06-01', 'net_loss' => null],
        ];
        $s = LossStats::summarize($events, '2026-11-21', self::THRESHOLDS);
        $this->assertSame(2, $s['losses']);
        $this->assertSame(1, $s['near_misses']);
        $this->assertSame(1, $s['gains']);
        $this->assertEqualsWithDelta(2 / 3, $s['frequency'], 1e-9);
        $this->assertSame(3, $s['prob_level']);
        $this->assertSame(12000000.0, $s['max_loss']);
        $this->assertEqualsWithDelta(15000000.5, $s['total_loss'], 1e-6);
        $this->assertSame(3, $s['impact_level']);
        $this->assertSame('2023-11-21', $s['since']);
    }

    public function testSummarizeWithoutLosses(): void
    {
        $s = LossStats::summarize([['event_type' => 'near_miss', 'occurrence_date' => '2026-01-01', 'net_loss' => 0]], '2026-11-21');
        $this->assertNull($s['prob_level']);
        $this->assertNull($s['impact_level']);
    }

    public function testCompare(): void
    {
        $this->assertSame('underestimated', LossStats::compare(1, 4));
        $this->assertSame('overestimated', LossStats::compare(5, 2));
        $this->assertSame('consistent', LossStats::compare(3, 3));
        $this->assertSame('no_data', LossStats::compare(3, null));
        $this->assertSame('no_data', LossStats::compare(0, 3));
    }
}

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

use GlpiPlugin\Erm\AcceptanceState;
use GlpiPlugin\Erm\ControlEval;
use GlpiPlugin\Erm\QuantStats;
use PHPUnit\Framework\TestCase;

final class QuantTest extends TestCase
{
    public function testAcceptanceState(): void
    {
        $this->assertSame('none', AcceptanceState::state('in_treatment', null, '2026-11-21'));
        $this->assertSame('no_date', AcceptanceState::state('accepted', null, '2026-11-21'));
        $this->assertSame('expired', AcceptanceState::state('accepted', '2026-11-20', '2026-11-21'));
        $this->assertSame('expiring', AcceptanceState::state('accepted', '2026-12-15', '2026-11-21'));
        $this->assertSame('valid', AcceptanceState::state('accepted', '2027-06-30', '2026-11-21'));
        $this->assertSame('2027-11-21', AcceptanceState::defaultUntil('2026-11-21'));
    }

    public function testControlEval(): void
    {
        $this->assertSame('none', ControlEval::summarize([])['status']);
        $this->assertSame('failing', ControlEval::summarize(['effective', 'ineffective', null])['status']);
        $this->assertSame('partial', ControlEval::summarize(['effective', 'partial'])['status']);
        $this->assertSame('effective', ControlEval::summarize(['effective', 'effective'])['status']);
        $this->assertSame('untested', ControlEval::summarize([null, ''])['status']);
        $s = ControlEval::summarize(['effective', 'partial', 'ineffective', null]);
        $this->assertSame([4, 1, 1, 1, 1], [$s['total'], $s['effective'], $s['partial'], $s['ineffective'], $s['untested']]);
    }

    public function testAssessedAle(): void
    {
        $this->assertEqualsWithDelta(0.375 * 150, QuantStats::assessedAle(3, 100, 200), 1e-9);
        $this->assertEqualsWithDelta(0.9 * 80, QuantStats::assessedAle(5, null, 80), 1e-9);
        $this->assertNull(QuantStats::assessedAle(0, 100, 200));
        $this->assertNull(QuantStats::assessedAle(3, null, null));
    }

    public function testHistoricalAle(): void
    {
        $this->assertEqualsWithDelta(4 / 3 * 28.25, QuantStats::historicalAle(4 / 3, [18, 31, 12, 52]), 1e-9);
        $this->assertNull(QuantStats::historicalAle(0, [10]));
        $this->assertNull(QuantStats::historicalAle(1, [0, -5]));
    }

    public function testMonteCarloIsReproducibleAndConsistent(): void
    {
        $losses = [18.0, 31.0, 12.0, 52.0];
        $a = QuantStats::simulate(4 / 3, $losses, 20000, 7);
        $b = QuantStats::simulate(4 / 3, $losses, 20000, 7);
        $this->assertNotNull($a);
        $this->assertSame($a, $b);
        // Mean close to the expected annual loss (lambda x average loss = 37.67)
        $this->assertEqualsWithDelta(4 / 3 * 28.25, $a['mean'], 2.0);
        // Probability of at least one loss close to 1 - e^-lambda = 0.736
        $this->assertEqualsWithDelta(1 - exp(-4 / 3), $a['prob_loss'], 0.02);
        $this->assertLessThanOrEqual($a['p90'], $a['p50']);
        $this->assertLessThanOrEqual($a['p99'], $a['p90']);
        $this->assertNull(QuantStats::simulate(0, $losses));
        $this->assertNull(QuantStats::simulate(1, []));
    }
}

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

use GlpiPlugin\Erm\Schedule;
use PHPUnit\Framework\TestCase;

final class ScheduleTest extends TestCase
{
    public function testNeverMeasuredIsDueToday(): void
    {
        $this->assertSame('2026-11-21', Schedule::nextMeasurement(null, 'monthly', '2026-11-21'));
    }

    public function testNextMeasurementByFrequency(): void
    {
        $this->assertSame('2026-11-30', Schedule::nextMeasurement('2026-10-31', 'monthly', '2026-11-21'));
        $this->assertSame('2027-02-28', Schedule::nextMeasurement('2027-01-31', 'monthly', '2026-11-21'));
        $this->assertSame('2027-01-31', Schedule::nextMeasurement('2026-10-31', 'quarterly', '2026-11-21'));
        $this->assertSame('2026-11-28', Schedule::nextMeasurement('2026-11-21', 'weekly', '2026-11-21'));
        $this->assertSame('2026-11-22', Schedule::nextMeasurement('2026-11-21', 'daily', '2026-11-21'));
        $this->assertSame('2026-12-15', Schedule::nextMeasurement('2026-11-15', 'unknown', '2026-11-21'));
    }

    public function testState(): void
    {
        $this->assertSame('none', Schedule::state(null, '2026-11-21'));
        $this->assertSame('overdue', Schedule::state('2026-11-20', '2026-11-21'));
        $this->assertSame('soon', Schedule::state('2026-11-21', '2026-11-21'));
        $this->assertSame('soon', Schedule::state('2026-12-21', '2026-11-21'));
        $this->assertSame('ok', Schedule::state('2026-12-22', '2026-11-21'));
        $this->assertSame('ok', Schedule::state('2026-11-29', '2026-11-21', 7));
    }
}

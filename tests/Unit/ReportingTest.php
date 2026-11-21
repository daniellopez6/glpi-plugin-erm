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

use GlpiPlugin\Erm\Consolidation;
use GlpiPlugin\Erm\Period;
use GlpiPlugin\Erm\SnapshotDiff;
use PHPUnit\Framework\TestCase;

final class ReportingTest extends TestCase
{
    public function testQuarters(): void
    {
        $this->assertSame('2026-Q4', Period::quarterOf('2026-11-21'));
        $this->assertSame('2026-Q1', Period::quarterOf('2026-01-01'));
        $this->assertSame('2026-Q3', Period::quarterOf('2026-09-30'));
        $this->assertSame('2026-Q3', Period::previous('2026-Q4'));
        $this->assertSame('2025-Q4', Period::previous('2026-Q1'));
        $this->assertSame('Q4 2026', Period::label('2026-Q4'));
        $this->assertFalse(Period::isValid('2026-Q5'));
        $this->assertFalse(Period::isValid("2026-Q1' OR 1=1"));
    }

    public function testSnapshotDiff(): void
    {
        $before = [
            1 => ['id' => 1, 'residual_level' => 'medium', 'appetite_position' => 'within'],
            2 => ['id' => 2, 'residual_level' => 'high', 'appetite_position' => 'above_appetite'],
            3 => ['id' => 3, 'residual_level' => 'low', 'appetite_position' => 'within'],
            4 => ['id' => 4, 'residual_level' => '', 'inherent_level' => 'medium', 'appetite_position' => 'within'],
        ];
        $after = [
            1 => ['id' => 1, 'residual_level' => 'high', 'appetite_position' => 'above_tolerance'],
            2 => ['id' => 2, 'residual_level' => 'medium', 'appetite_position' => 'within'],
            4 => ['id' => 4, 'residual_level' => 'medium', 'appetite_position' => 'within'],
            5 => ['id' => 5, 'residual_level' => 'critical', 'appetite_position' => 'above_tolerance'],
        ];
        $d = SnapshotDiff::compare($before, $after);
        $this->assertSame([5], array_column($d['new'], 'id'));
        $this->assertSame([3], array_column($d['removed'], 'id'));
        $this->assertSame([1], array_column($d['up'], 'id'));
        $this->assertSame('medium', $d['up'][0]['previous_level']);
        $this->assertSame([2], array_column($d['down'], 'id'));
        $this->assertSame([1], array_column($d['into_outside'], 'id'));
        $this->assertSame([2], array_column($d['back_within'], 'id'));
    }

    public function testConsolidation(): void
    {
        $this->assertSame('high', Consolidation::grcLevel(['residual_level' => 'high', 'risk_level' => 'critical']));
        $this->assertSame('critical', Consolidation::grcLevel(['residual_level' => '', 'risk_level' => 'critical']));
        $this->assertSame('', Consolidation::grcLevel(['risk_level' => 'unknown']));
        $this->assertSame('critical', Consolidation::highest(['low', 'critical', 'medium']));
        $this->assertTrue(Consolidation::belowLinked('medium', ['low', 'high']));
        $this->assertFalse(Consolidation::belowLinked('high', ['high', 'medium']));
        $this->assertFalse(Consolidation::belowLinked('low', []));
    }
}

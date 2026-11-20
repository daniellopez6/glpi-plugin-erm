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

use GlpiPlugin\Erm\Scale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScaleTest extends TestCase
{
    /**
     * @return array<string, array{0:int, 1:int, 2:string}>
     */
    public static function matrix(): array
    {
        return [
            'not assessed'   => [0, 3, ''],
            'min low'        => [1, 1, 'low'],
            'max low'        => [2, 2, 'low'],
            'min medium'     => [1, 5, 'medium'],
            'max medium'     => [3, 3, 'medium'],
            'min high'       => [2, 5, 'high'],
            'max high'       => [4, 4, 'high'],
            'min critical'   => [4, 5, 'critical'],
            'max critical'   => [5, 5, 'critical'],
        ];
    }

    #[DataProvider('matrix')]
    public function testLevelFor(int $probability, int $impact, string $expected): void
    {
        $this->assertSame($expected, Scale::levelFor($probability, $impact));
    }

    public function testRankOrder(): void
    {
        $this->assertLessThan(Scale::rank('medium'), Scale::rank('low'));
        $this->assertLessThan(Scale::rank('critical'), Scale::rank('high'));
        $this->assertSame(-1, Scale::rank('unknown'));
    }

    public function testAppetitePosition(): void
    {
        $this->assertSame('within', Scale::appetitePosition('medium', 'medium', 'high')[0]);
        $this->assertSame('above_appetite', Scale::appetitePosition('high', 'medium', 'high')[0]);
        $this->assertSame('above_tolerance', Scale::appetitePosition('critical', 'medium', 'high')[0]);
        $this->assertSame('undefined', Scale::appetitePosition('high', '', '')[0]);
        $this->assertSame('undefined', Scale::appetitePosition('', 'low', 'medium')[0]);
    }

    public function testBadgeEscapesAndColours(): void
    {
        $badge = Scale::badge('high', 12);
        $this->assertStringContainsString(Scale::color('high'), $badge);
        $this->assertStringContainsString('(12)', $badge);
    }
}

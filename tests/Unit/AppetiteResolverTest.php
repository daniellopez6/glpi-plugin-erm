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

use GlpiPlugin\Erm\AppetiteResolver;
use PHPUnit\Framework\TestCase;

/**
 * Category 10 (Financial, defaults medium/high) has a child 11 (Credit, no defaults).
 * Entity 3 is a child of entity 1, which is a child of the root entity 0.
 */
final class AppetiteResolverTest extends TestCase
{
    /** @var array<int, array{appetite_level:string, tolerance_level:string}> */
    private array $defaults = [
        10 => ['appetite_level' => 'medium', 'tolerance_level' => 'high'],
        11 => ['appetite_level' => '', 'tolerance_level' => ''],
    ];

    public function testDefaultsAreInheritedFromParentCategory(): void
    {
        $this->assertSame(['medium', 'high'], AppetiteResolver::resolve([11, 10], [3, 1, 0], [], $this->defaults));
    }

    public function testEntityOverrideOnParentCategoryApplies(): void
    {
        $overrides = [10 => [3 => ['appetite_level' => 'low', 'tolerance_level' => 'medium']]];
        $this->assertSame(['low', 'medium'], AppetiteResolver::resolve([11, 10], [3, 1, 0], $overrides, $this->defaults));
    }

    public function testOverrideIsInheritedFromParentEntity(): void
    {
        $overrides = [10 => [1 => ['appetite_level' => 'low', 'tolerance_level' => '']]];
        $this->assertSame(['low', 'high'], AppetiteResolver::resolve([11, 10], [3, 1, 0], $overrides, $this->defaults));
    }

    public function testClosestEntityWins(): void
    {
        $overrides = [10 => [
            1 => ['appetite_level' => 'low', 'tolerance_level' => 'low'],
            3 => ['appetite_level' => 'high', 'tolerance_level' => ''],
        ]];
        $this->assertSame(['high', 'low'], AppetiteResolver::resolve([11, 10], [3, 1, 0], $overrides, $this->defaults));
    }

    public function testOverrideOfAnotherEntityIsIgnored(): void
    {
        $overrides = [10 => [7 => ['appetite_level' => 'low', 'tolerance_level' => 'low']]];
        $this->assertSame(['medium', 'high'], AppetiteResolver::resolve([11, 10], [3, 1, 0], $overrides, $this->defaults));
    }

    public function testSubcategoryDefaultBeatsParentCategoryOverride(): void
    {
        $defaults = $this->defaults;
        $defaults[11] = ['appetite_level' => 'low', 'tolerance_level' => 'medium'];
        $overrides = [10 => [3 => ['appetite_level' => 'high', 'tolerance_level' => 'critical']]];
        $this->assertSame(['low', 'medium'], AppetiteResolver::resolve([11, 10], [3, 1, 0], $overrides, $defaults));
    }

    public function testNothingDefined(): void
    {
        $this->assertSame(['', ''], AppetiteResolver::resolve([99], [0], [], []));
    }
}

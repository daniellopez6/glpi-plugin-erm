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

use GlpiPlugin\Erm\DrillFilter;
use PHPUnit\Framework\TestCase;

final class DrillFilterTest extends TestCase
{
    /**
     * @return list<array<string,mixed>>
     */
    private function risks(): array
    {
        $base = ['inherent_probability' => 0, 'inherent_impact' => 0, 'residual_probability' => 0, 'residual_impact' => 0,
                 'inherent_level' => '', 'residual_level' => '', 'category' => '', 'entities_id' => 1, 'appetite' => ['within', '']];
        return [
            ['id' => 1, 'inherent_probability' => 4, 'inherent_impact' => 5, 'residual_probability' => 3, 'residual_impact' => 4,
             'inherent_level' => 'critical', 'residual_level' => 'high', 'category' => 'Financial > Credit',
             'appetite' => ['above_tolerance', '']] + $base,
            ['id' => 2, 'inherent_probability' => 3, 'inherent_impact' => 3, 'inherent_level' => 'medium',
             'category' => 'Operational', 'entities_id' => 2, 'appetite' => ['above_appetite', '']] + $base,
            ['id' => 3, 'inherent_probability' => 2, 'inherent_impact' => 2, 'residual_probability' => 1, 'residual_impact' => 2,
             'inherent_level' => 'low', 'residual_level' => 'low', 'category' => 'Financial'] + $base,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private function ids(array $rows): array
    {
        return array_column($rows, 'id');
    }

    public function testQueryIsSanitized(): void
    {
        $f = DrillFilter::fromQuery(['level' => 'huge', 'heat' => 'residual', 'p' => '9', 'i' => '1', 'entity' => '-3',
                                     'outside' => 'x', 'overdue' => '1', 'other' => 'y', 'category' => '']);
        $this->assertSame(['overdue' => 1], $f);
    }

    public function testValidQuery(): void
    {
        $f = DrillFilter::fromQuery(['level' => 'high', 'heat' => 'inherent', 'p' => '4', 'i' => '5', 'entity' => '0',
                                     'category' => 'Financial', 'outside' => 'any', 'kri' => '1']);
        $this->assertSame(['level' => 'high', 'heat' => 'inherent', 'p' => 4, 'i' => 5, 'category' => 'Financial',
                           'entity' => 0, 'outside' => 'any', 'kri' => 1], $f);
    }

    public function testNoFilterReturnsEverything(): void
    {
        $this->assertSame([1, 2, 3], $this->ids(DrillFilter::apply($this->risks(), [])));
    }

    public function testLevelUsesInherentWhenNotAssessed(): void
    {
        $this->assertSame([2], $this->ids(DrillFilter::apply($this->risks(), ['level' => 'medium'])));
        $this->assertSame([1], $this->ids(DrillFilter::apply($this->risks(), ['level' => 'high'])));
    }

    public function testHeatMapCell(): void
    {
        $this->assertSame([1], $this->ids(DrillFilter::apply($this->risks(), ['heat' => 'inherent', 'p' => 4, 'i' => 5])));
        $this->assertSame([], $this->ids(DrillFilter::apply($this->risks(), ['heat' => 'residual', 'p' => 4, 'i' => 5])));
    }

    public function testRootCategoryIncludesSubcategories(): void
    {
        $this->assertSame([1, 3], $this->ids(DrillFilter::apply($this->risks(), ['category' => 'Financial'])));
    }

    public function testEntityAndOutsideCombined(): void
    {
        $this->assertSame([1, 2], $this->ids(DrillFilter::apply($this->risks(), ['outside' => 'any'])));
        $this->assertSame([1], $this->ids(DrillFilter::apply($this->risks(), ['outside' => 'any', 'entity' => 1])));
        $this->assertSame([2], $this->ids(DrillFilter::apply($this->risks(), ['outside' => 'above_appetite'])));
    }

    public function testOverdueAndKri(): void
    {
        $this->assertSame([3], $this->ids(DrillFilter::apply($this->risks(), ['overdue' => 1], [3], [1])));
        $this->assertSame([1], $this->ids(DrillFilter::apply($this->risks(), ['kri' => 1], [3], [1])));
    }
}

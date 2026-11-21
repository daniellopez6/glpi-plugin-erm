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

use GlpiPlugin\Erm\AiImpact;
use PHPUnit\Framework\TestCase;

final class AiImpactTest extends TestCase
{
    public function testOverall(): void
    {
        $this->assertSame('', AiImpact::overall([]));
        $this->assertSame('', AiImpact::overall(['fairness' => 0, 'unknown' => 5]));
        $this->assertSame('low', AiImpact::overall(['fairness' => 2, 'privacy' => 1]));
        $this->assertSame('medium', AiImpact::overall(['fairness' => 3, 'privacy' => 2]));
        $this->assertSame('high', AiImpact::overall(['fairness' => 4, 'privacy' => 4, 'legal' => 2]));
        $this->assertSame('critical', AiImpact::overall(['fairness' => 4, 'privacy' => 4, 'legal' => 4]));
        $this->assertSame('critical', AiImpact::overall(['reliability' => 5]));
    }

    public function testWorstDimension(): void
    {
        $this->assertSame('reliability', AiImpact::worstDimension(['fairness' => 2, 'reliability' => 4, 'privacy' => 3]));
        $this->assertSame('', AiImpact::worstDimension(['fairness' => 0]));
    }

    public function testGap(): void
    {
        $this->assertSame('missing', AiImpact::gap('production', 'high', null, '2026-11-21', false));
        $this->assertSame('none', AiImpact::gap('production', 'high', '2027-06-30', '2026-11-21', true));
        $this->assertSame('expired', AiImpact::gap('production', 'limited', '2026-10-01', '2026-11-21', true));
        $this->assertSame('none', AiImpact::gap('production', 'minimal', null, '2026-11-21', false));
        $this->assertSame('none', AiImpact::gap('planned', 'high', null, '2026-11-21', false));
        $this->assertSame('prohibited', AiImpact::gap('planned', 'prohibited', null, '2026-11-21', false));
        $this->assertSame('none', AiImpact::gap('retired', 'prohibited', null, '2026-11-21', false));
    }

    public function testSegregation(): void
    {
        $this->assertFalse(AiImpact::canApprove(5, 5));
        $this->assertFalse(AiImpact::canApprove(5, 0));
        $this->assertTrue(AiImpact::canApprove(5, 7));
    }
}

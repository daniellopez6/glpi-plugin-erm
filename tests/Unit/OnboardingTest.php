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

use GlpiPlugin\Erm\Onboarding;
use PHPUnit\Framework\TestCase;

final class OnboardingTest extends TestCase
{
    public function testEmptyInstallation(): void
    {
        $steps = Onboarding::steps([]);
        $this->assertCount(9, $steps);
        $this->assertSame(0, Onboarding::progress($steps));
    }

    public function testCompleteInstallation(): void
    {
        $steps = Onboarding::steps(['impact_criteria' => true, 'appetite_categories' => 8, 'root_categories' => 8,
            'role_profiles_assigned' => true, 'risks' => 10, 'assessed_risks' => 8, 'kris' => 3, 'reviews' => 1, 'snapshots' => 1,
            'notifications' => true]);
        $this->assertSame(100, Onboarding::progress($steps));
    }

    public function testPartialRules(): void
    {
        $steps = array_column(Onboarding::steps(['risks' => 10, 'assessed_risks' => 7, 'root_categories' => 8, 'appetite_categories' => 7]), 'done', 'key');
        $this->assertTrue($steps['risks']);
        $this->assertFalse($steps['assessed']);
        $this->assertFalse($steps['appetite']);
        $this->assertFalse(Onboarding::steps(['risks' => 4])[3]['done']);
    }
}

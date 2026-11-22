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

use GlpiPlugin\Erm\RiskLibrary;
use GlpiPlugin\Erm\WizardMapper;
use PHPUnit\Framework\TestCase;

final class WizardTest extends TestCase
{
    public function testMapping(): void
    {
        $this->assertSame('high', WizardMapper::effectiveness('tested'));
        $this->assertSame('', WizardMapper::effectiveness('dont_know'));
        $this->assertSame(4, WizardMapper::residual('same', 4, 'tested'));
        $this->assertSame(4, WizardMapper::residual('2', 4, 'none'));
        $this->assertSame(2, WizardMapper::residual('2', 4, 'believe'));
        $this->assertSame(4, WizardMapper::residual('5', 4, 'believe'));
        $this->assertSame('identified', WizardMapper::status(0, 3, 'mitigate'));
        $this->assertSame('in_treatment', WizardMapper::status(3, 3, 'mitigate'));
        $this->assertSame('assessed', WizardMapper::status(3, 3, 'accept'));
    }

    public function testLibrariesAreCompleteAndParallel(): void
    {
        $es = RiskLibrary::load('es');
        $en = RiskLibrary::load('en');
        $this->assertGreaterThanOrEqual(20, count($es));
        $this->assertSame(array_keys($es), array_keys($en));
        // Categories must exist in the standard taxonomy (keys of Category::TAXONOMY, checked by name here)
        $roots = ['strategic', 'financial', 'operational', 'compliance', 'fraud', 'reputational', 'esg', 'ai'];
        foreach ([$es, $en] as $lib) {
            foreach ($lib as $key => $t) {
                foreach (['name', 'causes', 'consequences', 'controls', 'kris'] as $f) {
                    $this->assertNotSame('', trim($t[$f]), "$key.$f");
                }
                $this->assertContains(RiskLibrary::rootOf($t['category']), $roots, $key);
                $this->assertContains($t['treatment'], ['mitigate', 'transfer', 'avoid', 'accept'], $key);
                $this->assertGreaterThanOrEqual(4, count(preg_split('/\s+/u', $t['name']) ?: []), "$key name as event");
            }
        }
        $this->assertSame('es', RiskLibrary::languageFile('es_AR'));
        $this->assertSame('en', RiskLibrary::languageFile('fr_FR'));
    }
}

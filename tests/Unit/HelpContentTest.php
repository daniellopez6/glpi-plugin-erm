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

use GlpiPlugin\Erm\HelpContent;
use PHPUnit\Framework\TestCase;

final class HelpContentTest extends TestCase
{
    public function testContentIsCompleteAndParallel(): void
    {
        $es = HelpContent::load('es');
        $en = HelpContent::load('en');
        foreach (['roles', 'glossary', 'faq'] as $part) {
            $this->assertNotEmpty($es[$part], $part);
            $this->assertCount(count($es[$part]), $en[$part], $part);
        }
        $this->assertSame(array_column($es['roles'], 'key'), array_column($en['roles'], 'key'));
        foreach ([$es, $en] as $c) {
            foreach ($c['roles'] as $r) {
                $this->assertNotEmpty($r['tasks'], $r['key']);
                foreach ($r['tasks'] as $t) {
                    $this->assertContains($t['link'], HelpContent::LINKS, $r['key']);
                }
            }
        }
    }

    public function testRoleOfProfile(): void
    {
        $this->assertSame('owner', HelpContent::roleOfProfileComment('Ve y modifica sólo sus riesgos. [erm:owner]'));
        $this->assertSame('', HelpContent::roleOfProfileComment('Perfil estándar'));
    }
}

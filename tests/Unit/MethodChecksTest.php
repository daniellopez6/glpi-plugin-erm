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

use GlpiPlugin\Erm\MethodChecks;
use PHPUnit\Framework\TestCase;

final class MethodChecksTest extends TestCase
{
    /** @return array<string,mixed> */
    private function goodRisk(): array
    {
        return [
            'name' => 'Falla del proveedor del core que detiene la cobranza', 'causes' => 'Proveedor único', 'consequences' => 'Pérdida de ingresos',
            'users_id' => 5, 'inherent_score' => 16, 'residual_score' => 9, 'target_score' => 6, 'existing_controls' => 'Contrato con SLA',
            'treatment' => 'mitigate', 'justification' => '', 'review_date' => '2027-06-30',
        ];
    }

    public function testGoodRiskHasNoIssues(): void
    {
        $this->assertSame([], MethodChecks::check($this->goodRisk(), 2, 'above_appetite', '2026-11-22'));
    }

    public function testEachIssue(): void
    {
        $r = ['name' => 'Proveedores', 'causes' => '', 'consequences' => ' ', 'users_id' => 0, 'inherent_score' => 0, 'residual_score' => 0,
              'target_score' => 0, 'existing_controls' => '', 'treatment' => '', 'justification' => '', 'review_date' => ''];
        $this->assertSame(
            ['name_short', 'no_causes', 'no_consequences', 'no_owner', 'not_assessed', 'outside_without_treatment', 'no_review_date'],
            MethodChecks::check($r, 0, 'above_tolerance', '2026-11-22')
        );

        $r = $this->goodRisk();
        $r['residual_score'] = 20;
        $this->assertSame(['residual_above_inherent'], MethodChecks::check($r, 1, 'within', '2026-11-22'));

        $r = $this->goodRisk();
        $r['existing_controls'] = '';
        $r['target_score'] = 12;
        $this->assertSame(['residual_without_controls', 'target_above_residual'], MethodChecks::check($r, 1, 'within', '2026-11-22'));

        $r = $this->goodRisk();
        $this->assertSame(['treatment_without_actions'], MethodChecks::check($r, 0, 'within', '2026-11-22'));

        $r['treatment'] = 'accept';
        $r['review_date'] = '2026-01-01';
        $this->assertSame(['accept_without_justification', 'review_overdue'], MethodChecks::check($r, 0, 'within', '2026-11-22'));
    }

    public function testEveryIssueHasAMessage(): void
    {
        $this->assertCount(13, MethodChecks::messages());
    }
}

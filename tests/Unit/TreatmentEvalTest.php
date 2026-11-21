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

use GlpiPlugin\Erm\Reminder;
use GlpiPlugin\Erm\TreatmentEval;
use PHPUnit\Framework\TestCase;

final class TreatmentEvalTest extends TestCase
{
    public function testEvaluate(): void
    {
        $this->assertSame('no_target', TreatmentEval::evaluate(3, 1, 12, 0));
        $this->assertSame('reached', TreatmentEval::evaluate(3, 1, 6, 6));
        $this->assertSame('reached', TreatmentEval::evaluate(0, 0, 4, 6));
        $this->assertSame('no_plan', TreatmentEval::evaluate(0, 0, 12, 6));
        $this->assertSame('in_progress', TreatmentEval::evaluate(3, 2, 12, 6));
        $this->assertSame('ineffective', TreatmentEval::evaluate(3, 3, 12, 6));
        $this->assertSame('no_plan', TreatmentEval::evaluate(0, 0, 0, 6));
    }

    public function testProgress(): void
    {
        $this->assertSame(0, TreatmentEval::progress(0, 0));
        $this->assertSame(67, TreatmentEval::progress(3, 2));
        $this->assertSame(100, TreatmentEval::progress(2, 5));
    }

    public function testReminderThrottling(): void
    {
        $this->assertTrue(Reminder::shouldSend(null, '2026-11-21'));
        $this->assertFalse(Reminder::shouldSend('2026-11-15 06:00:00', '2026-11-21'));
        $this->assertTrue(Reminder::shouldSend('2026-11-14', '2026-11-21'));
    }

    public function testReviewAndAppetiteAlerts(): void
    {
        $this->assertSame('review_overdue', Reminder::reviewAlert('2026-11-01', '2026-11-21'));
        $this->assertSame('review_due', Reminder::reviewAlert('2026-12-05', '2026-11-21'));
        $this->assertNull(Reminder::reviewAlert('2026-12-06', '2026-11-21'));
        $this->assertNull(Reminder::reviewAlert(null, '2026-11-21'));
        $this->assertSame('above_tolerance', Reminder::appetiteAlert('above_tolerance'));
        $this->assertSame('above_appetite', Reminder::appetiteAlert('above_appetite'));
        $this->assertNull(Reminder::appetiteAlert('within'));
    }

    public function testActionAlert(): void
    {
        $this->assertSame('action_overdue', Reminder::actionAlert('2026-11-20', '2026-11-21'));
        $this->assertSame('action_due', Reminder::actionAlert('2026-11-28', '2026-11-21'));
        $this->assertNull(Reminder::actionAlert('2026-11-29', '2026-11-21'));
        $this->assertNull(Reminder::actionAlert(null, '2026-11-21'));
    }
}

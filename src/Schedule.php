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

namespace GlpiPlugin\Erm;

use DateTimeImmutable;

/**
 * Date rules for KRI measurements and risk reviews. Pure logic (no database) so it can be unit tested.
 */
final class Schedule
{
    /** Days before a due date from which an item is shown as "due soon". */
    public const SOON_DAYS = 30;

    private const DAYS = ['daily' => 1, 'weekly' => 7];
    private const MONTHS = ['monthly' => 1, 'quarterly' => 3];

    /**
     * Date of the next expected KRI measurement: last measurement + frequency, or today when never measured.
     */
    public static function nextMeasurement(?string $lastDate, string $frequency, string $today): string
    {
        if ($lastDate === null || $lastDate === '') {
            return $today;
        }
        $last = new DateTimeImmutable($lastDate);
        if (isset(self::DAYS[$frequency])) {
            return $last->modify('+' . self::DAYS[$frequency] . ' days')->format('Y-m-d');
        }
        // Month based: keep the day of month, clamped to the last day of the target month (31/10 -> 30/11)
        $months = self::MONTHS[$frequency] ?? 1;
        $target = $last->modify('first day of +' . $months . ' month');
        $day = min((int) $last->format('j'), (int) $target->format('t'));
        return $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day)->format('Y-m-d');
    }

    /**
     * State of a due date: overdue, due soon (within SOON_DAYS), ok, or none when there is no date.
     */
    public static function state(?string $dueDate, string $today, int $soonDays = self::SOON_DAYS): string
    {
        if ($dueDate === null || $dueDate === '') {
            return 'none';
        }
        if ($dueDate < $today) {
            return 'overdue';
        }
        $limit = (new DateTimeImmutable($today))->modify("+{$soonDays} days")->format('Y-m-d');
        return $dueDate <= $limit ? 'soon' : 'ok';
    }
}

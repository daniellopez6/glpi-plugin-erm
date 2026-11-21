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
 * Throttling of e-mail alerts: an alert about the same item is repeated at most every REPEAT_DAYS days.
 * Pure logic (no database) so it can be unit tested.
 */
final class Reminder
{
    public const REPEAT_DAYS = 7;
    public const DUE_SOON_DAYS = 7;
    public const REVIEW_NOTICE_DAYS = 14;

    public static function shouldSend(?string $lastSent, string $today, int $repeatDays = self::REPEAT_DAYS): bool
    {
        if ($lastSent === null || $lastSent === '') {
            return true;
        }
        $next = (new DateTimeImmutable(substr($lastSent, 0, 10)))->modify("+{$repeatDays} days")->format('Y-m-d');
        return $today >= $next;
    }

    /** Alert type of a risk review according to its date, or null. */
    public static function reviewAlert(?string $reviewDate, string $today): ?string
    {
        return match (Schedule::state($reviewDate, $today, self::REVIEW_NOTICE_DAYS)) {
            'overdue' => 'review_overdue',
            'soon'    => 'review_due',
            default   => null,
        };
    }

    /** Alert type of a residual appetite position (Scale::appetitePosition), or null. */
    public static function appetiteAlert(string $position): ?string
    {
        return in_array($position, ['above_appetite', 'above_tolerance'], true) ? $position : null;
    }

    /** Alert type of a pending action according to its due date, or null when nothing has to be said. */
    public static function actionAlert(?string $dueDate, string $today): ?string
    {
        $state = Schedule::state($dueDate, $today, self::DUE_SOON_DAYS);
        return match ($state) {
            'overdue' => 'action_overdue',
            'soon'    => 'action_due',
            default   => null,
        };
    }
}

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
 * Validity of the formal acceptance of a risk. An accepted risk must be accepted again before its validity ends.
 * Pure logic.
 */
final class AcceptanceState
{
    public const VALIDITY_MONTHS = 12;
    public const NOTICE_DAYS = 30;

    /**
     * @return string none (not accepted) | valid | expiring | expired | no_date
     */
    public static function state(string $status, ?string $validUntil, string $today): string
    {
        if ($status !== 'accepted') {
            return 'none';
        }
        if ($validUntil === null || $validUntil === '') {
            return 'no_date';
        }
        return match (Schedule::state($validUntil, $today, self::NOTICE_DAYS)) {
            'overdue' => 'expired',
            'soon'    => 'expiring',
            default   => 'valid',
        };
    }

    /** Default end of validity of a new acceptance. */
    public static function defaultUntil(string $today): string
    {
        return (new DateTimeImmutable($today))->modify('+' . self::VALIDITY_MONTHS . ' months')->format('Y-m-d');
    }
}

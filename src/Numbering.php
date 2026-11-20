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

/**
 * Risk code numbering: PREFIX-NNN, sequential per entity.
 */
final class Numbering
{
    public const DEFAULT_PREFIX = 'RC';

    /**
     * Normalizes a code prefix: uppercase letters, digits and dashes, 1 to 10 characters.
     */
    public static function normalizePrefix(?string $prefix): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $prefix) ?? '');
        $prefix = trim(substr($prefix, 0, 10), '-');
        return $prefix === '' ? self::DEFAULT_PREFIX : $prefix;
    }

    /**
     * Next code for a prefix, given the codes already used in the entity.
     *
     * @param list<string> $existingCodes
     */
    public static function next(array $existingCodes, string $prefix): string
    {
        $prefix = self::normalizePrefix($prefix);
        $max = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
        foreach ($existingCodes as $code) {
            if (preg_match($pattern, (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return sprintf('%s-%03d', $prefix, $max + 1);
    }
}

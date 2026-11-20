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

use Session;

/**
 * Common input validation (dates, numbers, mandatory texts).
 */
final class Validator
{
    /**
     * Normalizes a Y-m-d date; returns 'NULL' when empty or false when invalid.
     *
     * @param mixed $value
     * @return string|false
     */
    public static function date(mixed $value): string|false
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'NULL';
        }
        $d = \DateTime::createFromFormat('!Y-m-d', substr($value, 0, 10));
        return ($d && $d->format('Y-m-d') === substr($value, 0, 10)) ? substr($value, 0, 10) : false;
    }

    /**
     * Normalizes a decimal number; 'NULL' when empty, false when not numeric.
     *
     * @param mixed $value
     * @return string|false
     */
    public static function decimal(mixed $value): string|false
    {
        $value = str_replace(' ', '', trim((string) $value));
        if ($value === '') {
            return 'NULL';
        }
       // Accepts 1500000.5, 1500000,5, 1.500.000,50 (es_AR) and 1,500,000.50 (en).
        $lastComma = strrpos($value, ',');
        $lastDot   = strrpos($value, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif ($lastDot !== false && substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        return is_numeric($value) ? $value : false;
    }

    /**
     * Validates the given fields of $input; adds a message and returns false on the first error.
     *
     * @param array<string,mixed> $input
     * @param array<string,string> $rules campo => 'date'|'decimal'|'required'
     * @return array<string,mixed>|false
     */
    public static function apply(array $input, array $rules): array|false
    {
        foreach ($rules as $field => $rule) {
            if ($rule === 'required') {
                if (array_key_exists($field, $input) && trim((string) $input[$field]) === '') {
                    Session::addMessageAfterRedirect(sprintf(__('The field "%s" is mandatory.', 'erm'), $field), false, ERROR);
                    return false;
                }
                continue;
            }
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = $rule === 'date' ? self::date($input[$field]) : self::decimal($input[$field]);
            if ($value === false) {
                Session::addMessageAfterRedirect(sprintf(__('Invalid value in "%s".', 'erm'), $field), false, ERROR);
                return false;
            }
            $input[$field] = $value;
        }
        return $input;
    }

    /**
     * Integer value within a range, or the minimum when out of range.
     *
     * @param mixed $value
     */
    public static function range(mixed $value, int $min, int $max): int
    {
        $v = (int) $value;
        return ($v < $min || $v > $max) ? $min : $v;
    }
}

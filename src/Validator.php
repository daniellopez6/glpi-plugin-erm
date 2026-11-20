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
 * Validaciones comunes de entrada (fechas, números, textos obligatorios).
 */
final class Validator
{
    /**
     * Normaliza una fecha Y-m-d; devuelve 'NULL' si está vacía o false si es inválida.
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
     * Normaliza un número decimal; 'NULL' si está vacío, false si no es numérico.
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
        // Acepta 1500000.5, 1500000,5, 1.500.000,50 (es_AR) y 1,500,000.50 (en).
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
     * Valida los campos indicados de $input; agrega un mensaje y devuelve false ante el primer error.
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
                    Session::addMessageAfterRedirect(sprintf('El campo "%s" es obligatorio.', $field), false, ERROR);
                    return false;
                }
                continue;
            }
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = $rule === 'date' ? self::date($input[$field]) : self::decimal($input[$field]);
            if ($value === false) {
                Session::addMessageAfterRedirect(sprintf('Valor inválido en "%s".', $field), false, ERROR);
                return false;
            }
            $input[$field] = $value;
        }
        return $input;
    }

    /**
     * Valor entero dentro de un rango, o el mínimo si está fuera.
     *
     * @param mixed $value
     */
    public static function range(mixed $value, int $min, int $max): int
    {
        $v = (int) $value;
        return ($v < $min || $v > $max) ? $min : $v;
    }
}

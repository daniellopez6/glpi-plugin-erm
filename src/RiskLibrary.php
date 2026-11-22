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
 * Library of typical risks (data/risk-library/{lang}.json): ready-to-adapt templates with causes, consequences,
 * suggested controls and KRIs, used by the risk wizard. Pure logic (reads the plugin's own data files).
 */
final class RiskLibrary
{
    public const FIELDS = ['key', 'category', 'treatment', 'name', 'description', 'causes', 'consequences', 'controls', 'kris'];

    /** Language file for a GLPI language code (Spanish variants use "es", the rest English). */
    public static function languageFile(string $glpiLanguage): string
    {
        return str_starts_with(strtolower($glpiLanguage), 'es') ? 'es' : 'en';
    }

    public static function path(string $lang): string
    {
        return dirname(__DIR__) . '/data/risk-library/' . $lang . '.json';
    }

    /**
     * Templates of a language, indexed by key.
     *
     * @return array<string, array<string,string>>
     */
    public static function load(string $lang): array
    {
        $file = self::path($lang === 'es' ? 'es' : 'en');
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        $out = [];
        foreach ((array) ($data['templates'] ?? []) as $t) {
            if (!is_array($t) || empty($t['key'])) {
                continue;
            }
            $row = [];
            foreach (self::FIELDS as $f) {
                $row[$f] = (string) ($t[$f] ?? '');
            }
            $out[$row['key']] = $row;
        }
        return $out;
    }

    /** Root category key of a template category ("ai.bias" -> "ai"). */
    public static function rootOf(string $categoryKey): string
    {
        return explode('.', $categoryKey)[0];
    }
}

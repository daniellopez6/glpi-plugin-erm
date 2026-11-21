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
 * Summary of the last test of the controls protecting a risk (GLPI GRC Manager control tests). Pure logic.
 */
final class ControlEval
{
    public const RESULTS = ['effective', 'partial', 'ineffective'];

    /**
     * @param list<string|null> $lastResults last test result of each control (null = never tested)
     * @return array{total:int, effective:int, partial:int, ineffective:int, untested:int, status:string}
     *         status: none | failing | partial | effective | untested
     */
    public static function summarize(array $lastResults): array
    {
        $out = ['total' => count($lastResults), 'effective' => 0, 'partial' => 0, 'ineffective' => 0, 'untested' => 0];
        foreach ($lastResults as $r) {
            if (in_array($r, self::RESULTS, true)) {
                $out[$r]++;
            } else {
                $out['untested']++;
            }
        }
        $out['status'] = match (true) {
            $out['total'] === 0      => 'none',
            $out['ineffective'] > 0  => 'failing',
            $out['partial'] > 0      => 'partial',
            $out['effective'] > 0    => 'effective',
            default                  => 'untested',
        };
        return $out;
    }
}

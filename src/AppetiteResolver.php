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
 * Resolves the effective appetite and tolerance of a category for an entity.
 *
 * Precedence, for each value independently: the closest entity override (the entity itself, then its parent
 * entities) on the category itself, then the same on its parent categories, and finally the default values
 * stored on the categories.
 */
final class AppetiteResolver
{
    /**
     * @param list<int> $categoryChain   category id followed by its ancestors (closest first)
     * @param list<int> $entityChain     entity id followed by its ancestors (closest first)
     * @param array<int, array<int, array{appetite_level?:string, tolerance_level?:string}>> $overrides
     *        overrides indexed by category id then entity id
     * @param array<int, array{appetite_level?:string, tolerance_level?:string}> $defaults defaults by category id
     * @return array{0:string,1:string} [appetite, tolerance]
     */
    public static function resolve(array $categoryChain, array $entityChain, array $overrides, array $defaults): array
    {
        $result = ['appetite_level' => '', 'tolerance_level' => ''];
        foreach ($categoryChain as $category) {
            foreach ($entityChain as $entity) {
                foreach ($result as $field => $value) {
                    if ($value === '' && !empty($overrides[$category][$entity][$field])) {
                        $result[$field] = (string) $overrides[$category][$entity][$field];
                    }
                }
            }
            foreach ($result as $field => $value) {
                if ($value === '' && !empty($defaults[$category][$field])) {
                    $result[$field] = (string) $defaults[$category][$field];
                }
            }
            if ($result['appetite_level'] !== '' && $result['tolerance_level'] !== '') {
                break;
            }
        }
        return [$result['appetite_level'], $result['tolerance_level']];
    }
}

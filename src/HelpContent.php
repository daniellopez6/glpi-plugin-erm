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
 * Help content (data/help/{lang}.json): guides by role, glossary and frequently asked questions. Pure logic.
 */
final class HelpContent
{
    /** Pages a role task can link to. */
    public const LINKS = ['dashboard', 'myspace', 'wizard', 'library', 'risks', 'actions', 'events', 'concern', 'ai',
                          'consolidated', 'report', 'categories', 'methodology'];

    /**
     * @return array{roles: list<array<string,mixed>>, glossary: list<array<string,string>>, faq: list<array<string,string>>}
     */
    public static function load(string $lang): array
    {
        $file = dirname(__DIR__) . '/data/help/' . ($lang === 'es' ? 'es' : 'en') . '.json';
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        return [
            'roles'    => array_values((array) ($data['roles'] ?? [])),
            'glossary' => array_values((array) ($data['glossary'] ?? [])),
            'faq'      => array_values((array) ($data['faq'] ?? [])),
        ];
    }

    /** Role guide of a profile, from the marker of the ERM role profiles ([erm:key] in its comment). */
    public static function roleOfProfileComment(string $comment): string
    {
        return preg_match('/\[erm:([a-z_]+)\]/', $comment, $m) ? $m[1] : '';
    }
}

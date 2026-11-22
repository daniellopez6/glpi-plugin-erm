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

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;

/**
 * "Help and glossary" page: what to do by role, glossary in plain language and frequently asked questions.
 */
class Help extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Help and glossary', 'erm');
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    public static function getIcon()
    {
        return 'ti ti-help';
    }

    public static function canView(): bool
    {
        return Risk::canView();
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/help.php', $full);
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        return ['title' => self::getMenuName(), 'page' => self::getSearchURL(false), 'icon' => self::getIcon()];
    }

    /** Role guide key of the active profile ('' when it is not an ERM role profile). */
    public static function currentRole(): string
    {
        $profile = new \Profile();
        $id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        return $id && $profile->getFromDB($id) ? HelpContent::roleOfProfileComment((string) $profile->fields['comment']) : '';
    }

    public static function show(): void
    {
        $content = HelpContent::load(RiskLibrary::languageFile((string) ($_SESSION['glpilanguage'] ?? 'en_GB')));
        $links = [
            'dashboard'    => Dashboard::getSearchURL(),
            'myspace'      => MySpace::getSearchURL(),
            'wizard'       => RiskWizard::getWizardURL(),
            'library'      => RiskWizard::getLibraryURL(),
            'risks'        => Risk::getSearchURL(),
            'actions'      => Action::getSearchURL(),
            'events'       => Event::getSearchURL(),
            'concern'      => Concern::getSearchURL(),
            'ai'           => AiSystem::getSearchURL(),
            'consolidated' => Consolidated::getSearchURL(),
            'report'       => Report::getSearchURL(),
            'categories'   => Category::getSearchURL(),
            'methodology'  => Methodology::getURL(),
        ];
        TemplateRenderer::getInstance()->display('@erm/help.html.twig', $content + [
            'links'        => $links,
            'current_role' => self::currentRole(),
            'methodology_url' => Methodology::getURL(),
        ]);
    }
}

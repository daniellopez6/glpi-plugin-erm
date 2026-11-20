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
use Profile as GlpiProfile;

/**
 * "Corporate risks (ERM)" tab on the profile form: matrix with the four plugin rights.
 */
class Profile extends CommonGLPI
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (
            $item instanceof GlpiProfile && !$item->isNewItem() && ($item->fields['interface'] ?? '') === 'central'
            && GlpiProfile::canView()
        ) {
            return self::createTabEntry(__('Corporate risks (ERM)', 'erm'), 0, null, 'ti ti-building-bank');
        }
        return '';
    }

    /**
     * Rights matrix. Labels are identical on every row so that GLPI groups each right
     * in a single column.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRightsMatrix(): array
    {
        $read       = ['short' => __('See all', 'erm'), 'long' => __('See all items', 'erm')];
        $readOwn    = ['short' => __('See own', 'erm'), 'long' => __('See only own risks (owner, owner group or responsible of actions/KRIs)', 'erm')];
        $update     = ['short' => 'Actualizar', 'long' => __('Update the items the user can see', 'erm')];
        $updateOwn  = ['short' => __('Update own', 'erm'), 'long' => __('Update only own risks', 'erm')];
        $create     = ['short' => 'Crear', 'long' => __('Create items', 'erm')];
        $trash      = ['short' => 'Papelera', 'long' => __('Put in trashbin', 'erm')];
        $purge      = ['short' => 'Purgar', 'long' => __('Delete permanently', 'erm')];
        return [
           [
               'itemtype' => Risk::class,
               'label'    => __('Corporate risk register', 'erm'),
               'field'    => Rights::RISK,
               'rights'   => [READ => $read, READ_OWNED => $readOwn, UPDATE => $update, UPDATE_OWNED => $updateOwn,
                              CREATE => $create, DELETE => $trash, PURGE => $purge,
                              READNOTE => ['short' => __('Read notes', 'erm'), 'long' => __('Read the notes', 'erm')],
                              UPDATENOTE => ['short' => __('Update notes', 'erm'), 'long' => __('Update the notes', 'erm')]],
           ],
           [
               'itemtype' => Action::class,
               'label'    => __('Treatment and monitoring (actions, KRIs and measurements)', 'erm'),
               'field'    => Rights::TREATMENT,
               'rights'   => [CREATE => $create, UPDATE => $update, PURGE => $purge],
           ],
           [
               'itemtype' => Category::class,
               'label'    => __('Categories, appetite and tolerance', 'erm'),
               'field'    => Rights::CONFIG,
               'rights'   => [READ => $read, UPDATE => $update, CREATE => $create, DELETE => $trash, PURGE => $purge],
           ],
           [
               'itemtype' => Risk::class,
               'label'    => __('Formal risk acceptance (Committee / Board)', 'erm'),
               'field'    => Rights::ACCEPT,
               'rights'   => [UPDATE => ['short' => __('Accept risks', 'erm'), 'long' => __('Accept or revoke the acceptance of a risk (never an own risk)', 'erm')]],
           ],
        ];
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        TemplateRenderer::getInstance()->display('@erm/profile_rights.html.twig', [
           'item'   => $item,
           'rights' => self::getRightsMatrix(),
        ]);
        return true;
    }
}

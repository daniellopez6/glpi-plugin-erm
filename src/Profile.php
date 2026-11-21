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
use ProfileRight;

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
               'itemtype' => Event::class,
               'label'    => __('Loss events and incidents', 'erm'),
               'field'    => Rights::EVENT,
               'rights'   => [READ => $read, UPDATE => $update, CREATE => $create, DELETE => $trash, PURGE => $purge,
                              READNOTE => ['short' => __('Read notes', 'erm'), 'long' => __('Read the notes', 'erm')],
                              UPDATENOTE => ['short' => __('Update notes', 'erm'), 'long' => __('Update the notes', 'erm')]],
           ],
           [
               'itemtype' => AiSystem::class,
               'label'    => __('AI systems and impact assessments', 'erm'),
               'field'    => Rights::AI,
               'rights'   => [READ => $read, UPDATE => $update, CREATE => $create, DELETE => $trash, PURGE => $purge,
                              READNOTE => ['short' => __('Read notes', 'erm'), 'long' => __('Read the notes', 'erm')],
                              UPDATENOTE => ['short' => __('Update notes', 'erm'), 'long' => __('Update the notes', 'erm')],
                              Rights::APPROVE => ['short' => __('Approve assessments', 'erm'), 'long' => __('Approve or reject AI impact assessments (never own ones)', 'erm')]],
           ],
           [
               'itemtype' => Risk::class,
               'label'    => __('Formal risk acceptance (Committee / Board)', 'erm'),
               'field'    => Rights::ACCEPT,
               'rights'   => [UPDATE => ['short' => __('Accept risks', 'erm'), 'long' => __('Accept or revoke the acceptance of a risk (never an own risk)', 'erm')]],
           ],
        ];
    }

    /**
     * Ready-to-assign role profiles created at installation. Each one is recognised by a marker in its comment,
     * so it is created only once and never modified afterwards (administrators can adapt or rename it).
     *
     * @return array<string, array{name:string, comment:string, rights:array<string,int>}>
     */
    public static function roleProfiles(): array
    {
        $own = READ_OWNED | UPDATE_OWNED;
        $all = ALLSTANDARDRIGHT;
        $notes = READNOTE | UPDATENOTE;
        // Minimal GLPI navigation: no inventory (assets) at all
        $base = ['knowbase' => READ, 'personalization' => READ | UPDATE, 'password_update' => 1, 'document' => READ | CREATE];
        $itil = ['ticket' => \Ticket::READALL, 'problem' => READ, 'change' => READ];
        return [
            'manager' => [
                'name'    => 'ERM - ' . __('Risk manager', 'erm'),
                'comment' => __('Manages the corporate risk process: register, treatment, taxonomy and appetite, loss events and entity settings. Does not formally accept risks. No access to the inventory.', 'erm'),
                'rights'  => $base + $itil + ['entity' => READ, 'user' => READ, 'group' => READ,
                    Rights::RISK => $all | $notes, Rights::TREATMENT => $all, Rights::CONFIG => $all,
                    Rights::ACCEPT => 0, Rights::EVENT => $all | $notes, Rights::AI => $all | $notes],
            ],
            'owner' => [
                'name'    => 'ERM - ' . __('Risk owner', 'erm'),
                'comment' => __('Sees and updates only own risks (owner, owner group or responsible of actions/KRIs), manages their treatment and reports loss events. No access to the inventory.', 'erm'),
                'rights'  => $base + $itil + [Rights::RISK => $own, Rights::TREATMENT => CREATE | UPDATE, Rights::CONFIG => 0,
                    Rights::ACCEPT => 0, Rights::EVENT => READ | CREATE, Rights::AI => READ],
            ],
            'treatment' => [
                'name'    => 'ERM - ' . __('Treatment responsible', 'erm'),
                'comment' => __('Executes treatment actions and measures KRIs: sees the risks where they have actions or indicators, updates them and reports loss events.', 'erm'),
                'rights'  => $base + [Rights::RISK => READ_OWNED, Rights::TREATMENT => UPDATE | CREATE, Rights::CONFIG => 0,
                    Rights::ACCEPT => 0, Rights::EVENT => READ | CREATE, Rights::AI => 0],
            ],
            'committee' => [
                'name'    => 'ERM - ' . __('Risk Committee', 'erm'),
                'comment' => __('Board or Risk Committee: sees the whole register, approves appetite and tolerance and formally accepts risks (never own risks).', 'erm'),
                'rights'  => ['knowbase' => READ, 'personalization' => READ | UPDATE, 'password_update' => 1, 'document' => READ,
                    Rights::RISK => READ | READNOTE, Rights::TREATMENT => 0, Rights::CONFIG => READ | UPDATE,
                    Rights::ACCEPT => UPDATE, Rights::EVENT => READ, Rights::AI => READ | READNOTE | Rights::APPROVE],
            ],
            'audit' => [
                'name'    => 'ERM - ' . __('Internal audit', 'erm'),
                'comment' => __('Read-only access to the whole register, notes, appetite, treatment plans, reviews and loss events, with the related tickets as evidence.', 'erm'),
                'rights'  => ['knowbase' => READ, 'personalization' => READ | UPDATE, 'password_update' => 1, 'document' => READ] + $itil
                    + [Rights::RISK => READ | READNOTE, Rights::TREATMENT => 0, Rights::CONFIG => READ,
                       Rights::ACCEPT => 0, Rights::EVENT => READ | READNOTE, Rights::AI => READ | READNOTE],
            ],
            'ai' => [
                'name'    => 'ERM - ' . __('AI officer', 'erm'),
                'comment' => __('Keeps the inventory of AI systems and their impact assessments (ISO/IEC 42001), and manages the risks of their own AI systems. Assessments are approved by the AI or Risk Committee. No access to the inventory.', 'erm'),
                'rights'  => $base + [Rights::RISK => $own, Rights::TREATMENT => CREATE | UPDATE, Rights::CONFIG => 0,
                    Rights::ACCEPT => 0, Rights::EVENT => READ | CREATE, Rights::AI => READ | CREATE | UPDATE | READNOTE | UPDATENOTE,
                    'contact_enterprise' => READ],
            ],
        ];
    }

    /** Marker stored in the comment of a role profile. */
    public static function marker(string $key): string
    {
        return '[erm:' . $key . ']';
    }

    /**
     * Creates the missing role profiles.
     *
     * @return list<string> names of the created profiles
     */
    public static function createRoleProfiles(): array
    {
        $created = [];
        foreach (self::roleProfiles() as $key => $def) {
            $marker = self::marker($key);
            if (countElementsInTable(GlpiProfile::getTable(), ['comment' => ['LIKE', '%' . $marker . '%']]) > 0) {
                continue;
            }
            $profile = new GlpiProfile();
            $id = $profile->add([
                'name'      => $def['name'],
                'interface' => 'central',
                'comment'   => $def['comment'] . ' ' . $marker,
            ]);
            if ($id) {
                ProfileRight::updateProfileRights((int) $id, $def['rights']);
                $created[] = $def['name'];
            }
        }
        return $created;
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

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
 * Pestaña "Riesgos corporativos (ERM)" en la ficha del perfil: matriz con los cuatro derechos del plugin.
 */
class Profile extends CommonGLPI
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile && !$item->isNewItem() && ($item->fields['interface'] ?? '') === 'central'
            && GlpiProfile::canView()) {
            return self::createTabEntry('Riesgos corporativos (ERM)', 0, null, 'ti ti-building-bank');
        }
        return '';
    }

    /**
     * Matriz de derechos. Las etiquetas son las mismas en todas las filas para que GLPI agrupe cada derecho
     * en una sola columna.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRightsMatrix(): array
    {
        $read       = ['short' => 'Ver todos', 'long' => 'Ver todos los elementos'];
        $readOwn    = ['short' => 'Ver propios', 'long' => 'Ver sólo los riesgos propios (dueño, área o responsable de acciones/KRI)'];
        $update     = ['short' => 'Actualizar', 'long' => 'Modificar los elementos que puede ver'];
        $updateOwn  = ['short' => 'Actualizar propios', 'long' => 'Modificar sólo los riesgos propios'];
        $create     = ['short' => 'Crear', 'long' => 'Crear elementos'];
        $trash      = ['short' => 'Papelera', 'long' => 'Enviar a la papelera'];
        $purge      = ['short' => 'Purgar', 'long' => 'Eliminar definitivamente'];
        return [
            [
                'itemtype' => Risk::class,
                'label'    => 'Registro de riesgos corporativos',
                'field'    => Rights::RISK,
                'rights'   => [READ => $read, READ_OWNED => $readOwn, UPDATE => $update, UPDATE_OWNED => $updateOwn,
                               CREATE => $create, DELETE => $trash, PURGE => $purge,
                               READNOTE => ['short' => 'Leer notas', 'long' => 'Leer las notas'],
                               UPDATENOTE => ['short' => 'Actualizar notas', 'long' => 'Modificar las notas']],
            ],
            [
                'itemtype' => Action::class,
                'label'    => 'Tratamiento y seguimiento (acciones, KRI y mediciones)',
                'field'    => Rights::TREATMENT,
                'rights'   => [CREATE => $create, UPDATE => $update, PURGE => $purge],
            ],
            [
                'itemtype' => Category::class,
                'label'    => 'Categorías, apetito y tolerancia',
                'field'    => Rights::CONFIG,
                'rights'   => [READ => $read, UPDATE => $update, CREATE => $create, DELETE => $trash, PURGE => $purge],
            ],
            [
                'itemtype' => Risk::class,
                'label'    => 'Aceptación formal de riesgos (Comité / Directorio)',
                'field'    => Rights::ACCEPT,
                'rights'   => [UPDATE => ['short' => 'Aceptar riesgos', 'long' => 'Aceptar o revocar la aceptación de un riesgo (nunca el propio)']],
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

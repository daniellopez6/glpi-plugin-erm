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

use CommonDBChild;

/**
 * Medición de un KRI en una fecha.
 */
class IndicatorValue extends CommonDBChild
{
    public static string $itemtype = Indicator::class;
    public static string $items_id = 'plugin_erm_indicators_id';
    public static string $rightname = Rights::TREATMENT;
    public static int $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;

    public static function canView(): bool
    {
        return Risk::canView();
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Mediciones de KRI' : 'Medición de KRI';
    }

    public function prepareInputForAdd($input)
    {
        if (!isset($input['value']) || Validator::decimal($input['value']) === false || Validator::decimal($input['value']) === 'NULL') {
            \Session::addMessageAfterRedirect('La medición debe tener un valor numérico.', false, ERROR);
            return false;
        }
        $input['value'] = Validator::decimal($input['value']);
        $date = Validator::date($input['value_date'] ?? '');
        if ($date === false) {
            \Session::addMessageAfterRedirect('Fecha de medición inválida.', false, ERROR);
            return false;
        }
        $input['value_date'] = $date === 'NULL' ? date('Y-m-d') : $date;
        $input['date_creation'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }
}

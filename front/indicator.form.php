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

use GlpiPlugin\Erm\Indicator;
use GlpiPlugin\Erm\IndicatorValue;

if (isset($_POST['add_value'])) {
    $value = new IndicatorValue();
    $value->check(-1, CREATE, $_POST);
    $value->add($_POST);
} elseif (isset($_POST['add'])) {
    $item = new Indicator();
    $item->check(-1, CREATE, $_POST);
    $item->add($_POST);
} elseif (isset($_POST['measure_now'])) {
    $item = new Indicator();
    $item->check((int) $_POST['id'], READ);
    $value = new IndicatorValue();
    $valueInput = ['plugin_erm_indicators_id' => (int) $_POST['id']];
    $value->check(-1, CREATE, $valueInput);
    if ($item->fields['source'] === 'savedsearch' && Indicator::recordAutomatic($item->fields)) {
        Session::addMessageAfterRedirect(__('Automatic KRI measured.', 'erm'));
    } else {
        Session::addMessageAfterRedirect(__('The saved search of the KRI could not be executed.', 'erm'), false, ERROR);
    }
} elseif (isset($_POST['purge'])) {
    $item = new Indicator();
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, true);
}
Html::back();

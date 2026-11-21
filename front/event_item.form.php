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

use GlpiPlugin\Erm\Event_Item;

$link = new Event_Item();
if (isset($_POST['add'])) {
    if (!Event_Item::canEditEvent((int) ($_POST['plugin_erm_events_id'] ?? 0))) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    $link->add($_POST);
} elseif (isset($_POST['purge'])) {
    if (!$link->getFromDB((int) $_POST['id']) || !Event_Item::canEditEvent((int) $link->fields['plugin_erm_events_id'])) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    $link->delete(['id' => $link->getID()], true);
}
Html::back();

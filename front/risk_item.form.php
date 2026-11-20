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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Erm\Risk_Item;

$item = new Risk_Item();
if (isset($_POST['add'])) {
    if (!Risk_Item::canEditRisk((int) ($_POST['plugin_erm_risks_id'] ?? 0))) {
        throw new AccessDeniedHttpException();
    }
    $item->add($_POST);
} elseif (isset($_POST['purge'])) {
    if (!$item->getFromDB((int) $_POST['id']) || !Risk_Item::canEditRisk((int) $item->fields['plugin_erm_risks_id'])) {
        throw new AccessDeniedHttpException();
    }
    $item->delete(['id' => $item->getID()], true);
}
Html::back();

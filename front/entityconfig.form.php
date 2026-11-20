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
use GlpiPlugin\Erm\EntityConfig;
use GlpiPlugin\Erm\Rights;

$entityId = (int) ($_POST['entities_id'] ?? -1);
if (!isset($_POST['update']) || $entityId < 0) {
    Html::back();
}
if (!Session::haveRight(Rights::CONFIG, UPDATE) || !Session::haveAccessToEntity($entityId)) {
    throw new AccessDeniedHttpException();
}
EntityConfig::saveFromForm($entityId, $_POST);
Session::addMessageAfterRedirect(__('ERM settings saved.', 'erm'));
Html::back();

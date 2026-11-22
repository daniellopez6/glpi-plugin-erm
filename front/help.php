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

use GlpiPlugin\Erm\Dashboard;
use GlpiPlugin\Erm\Help;

if (!Help::canView()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}
Html::header(Help::getTypeName(), $_SERVER['PHP_SELF'], plugin_erm_sector(), Dashboard::class, Help::class);
Help::show();
Html::footer();

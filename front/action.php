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

use GlpiPlugin\Erm\Action;
use GlpiPlugin\Erm\Dashboard;

if (!Action::canView()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}
Html::header(Action::getMenuName(), $_SERVER['PHP_SELF'], plugin_erm_sector(), Dashboard::class, Action::class);
Search::show(Action::class);
Html::footer();

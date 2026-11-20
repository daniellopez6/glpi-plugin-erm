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

if (!GlpiPlugin\Erm\Category::canView()) {
    throw new Glpi\Exception\Http\AccessDeniedHttpException();
}
$dropdown = new GlpiPlugin\Erm\Category();
include(GLPI_ROOT . '/front/dropdown.common.form.php');

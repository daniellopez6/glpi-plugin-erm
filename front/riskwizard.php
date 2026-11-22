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
use GlpiPlugin\Erm\Risk;
use GlpiPlugin\Erm\RiskWizard;

if (!Risk::canCreate()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}
if (isset($_POST['create'])) {
    $id = RiskWizard::create($_POST);
    if ($id) {
        Session::addMessageAfterRedirect(__('Risk registered. Check the suggestions on the form to complete it.', 'erm'));
        Html::redirect(Risk::getFormURLWithID($id));
    }
    Html::back();
}
Html::header(RiskWizard::getTypeName(), $_SERVER['PHP_SELF'], plugin_erm_sector(), Dashboard::class, Risk::class);
RiskWizard::show((string) ($_GET['template'] ?? ''));
Html::footer();

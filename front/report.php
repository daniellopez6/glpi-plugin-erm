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
use GlpiPlugin\Erm\Period;
use GlpiPlugin\Erm\Report;
use GlpiPlugin\Erm\Snapshot;

if (!Report::canView()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}
if (isset($_POST['take_snapshot'])) {
    if (!Report::canTakeSnapshot()) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    $period = Period::quarterOf(date('Y-m-d'));
    // A manual snapshot only covers the active entities of the user
    $n = Snapshot::take($period, array_map('intval', $_SESSION['glpiactiveentities'] ?? []));
    Session::addMessageAfterRedirect(sprintf(__('Snapshot of %1$s taken (%2$d risks).', 'erm'), Period::label($period), $n));
    Html::back();
}
if (isset($_GET['pdf'])) {
    Report::pdf((string) ($_GET['compare'] ?? ''));
    return;
}
Html::header(Report::getTypeName(), $_SERVER['PHP_SELF'], plugin_erm_sector(), Dashboard::class, Report::class);
Report::show();
Html::footer();

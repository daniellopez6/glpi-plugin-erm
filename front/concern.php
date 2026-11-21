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

use GlpiPlugin\Erm\Concern;
use GlpiPlugin\Erm\Dashboard;

// Any authenticated user can report (also from the simplified interface)
Session::checkLoginUser();

if (isset($_POST['submit_report'])) {
    if (Concern::submit($_POST)) {
        Session::addMessageAfterRedirect(__('Thank you: your report was sent to the risk team.', 'erm'));
    }
    Html::back();
}
if (isset($_POST['to_event']) || isset($_POST['to_risk'])) {
    $url = isset($_POST['to_event']) ? Concern::toEvent((int) $_POST['id']) : Concern::toRisk((int) $_POST['id']);
    if ($url === '') {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    Html::redirect($url);
}
if (isset($_POST['reject'])) {
    if (!Concern::canReview()) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    if (Concern::reject((int) $_POST['id'], (string) ($_POST['review_comment'] ?? ''))) {
        Session::addMessageAfterRedirect(__('Report closed.', 'erm'));
    }
    Html::back();
}

if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpHeader(__('Report a risk or an incident', 'erm'));
} else {
    Html::header(__('Report a risk or an incident', 'erm'), $_SERVER['PHP_SELF'], plugin_erm_sector(), Dashboard::class, Concern::class);
}
Concern::show();
Session::getCurrentInterface() === 'helpdesk' ? Html::helpFooter() : Html::footer();

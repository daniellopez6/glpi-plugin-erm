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

use GlpiPlugin\Erm\AiAssessment;

$item = new AiAssessment();
if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    $id = $item->add($_POST);
    Html::redirect($id ? $item->getLinkURL() : Html::getBackUrl());
} elseif (isset($_POST['update'])) {
    $item->check((int) $_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['submit_approval'])) {
    $item->check((int) $_POST['id'], UPDATE);
    if ($item->update(['_submit' => 1] + $_POST)) {
        Session::addMessageAfterRedirect(__('Assessment submitted for approval.', 'erm'));
    }
    Html::back();
} elseif (isset($_POST['approve']) || isset($_POST['reject'])) {
    $item->check((int) $_POST['id'], READ);
    if ($item->decide(isset($_POST['approve']), trim((string) ($_POST['approval_comment'] ?? '')))) {
        Session::addMessageAfterRedirect(isset($_POST['approve']) ? __('Assessment approved', 'erm') : __('Assessment rejected', 'erm'));
    }
    Html::back();
} elseif (isset($_POST['create_risk'])) {
    $item->check((int) $_POST['id'], READ);
    $risk = new GlpiPlugin\Erm\Risk();
    $risk->check(-1, CREATE, $_POST);
    $rid = $item->createRisk();
    Html::redirect($rid ? GlpiPlugin\Erm\Risk::getFormURLWithID($rid) : Html::getBackUrl());
} elseif (isset($_POST['purge'])) {
    $item->check((int) $_POST['id'], PURGE);
    $item->delete($_POST, true);
    Html::redirect(GlpiPlugin\Erm\AiSystem::getSearchURL());
} else {
    if (!AiAssessment::canView()) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    Html::header(AiAssessment::getTypeName(1), $_SERVER['PHP_SELF'], plugin_erm_sector(), GlpiPlugin\Erm\Dashboard::class, GlpiPlugin\Erm\AiSystem::class);
    $item->display(['id' => (int) ($_GET['id'] ?? 0), 'plugin_erm_aisystems_id' => (int) ($_GET['plugin_erm_aisystems_id'] ?? 0)]);
    Html::footer();
}

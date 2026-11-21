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

use GlpiPlugin\Erm\Event;

$item = new Event();
if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    $id = $item->add($_POST);
    Html::redirect($id ? $item->getLinkURL() : Event::getSearchURL());
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST);
    $item->redirectToList();
} elseif (isset($_POST['restore'])) {
    $item->check($_POST['id'], DELETE);
    $item->restore($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, true);
    $item->redirectToList();
} else {
    if (!Event::canView()) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    Html::header(Event::getTypeName(1), $_SERVER['PHP_SELF'], plugin_erm_sector(), GlpiPlugin\Erm\Dashboard::class, Event::class);
    $item->display([
        'id'             => (int) ($_GET['id'] ?? 0),
        '_link_itemtype' => (string) ($_GET['_link_itemtype'] ?? ''),
        '_link_items_id' => (int) ($_GET['_link_items_id'] ?? 0),
    ]);
    Html::footer();
}

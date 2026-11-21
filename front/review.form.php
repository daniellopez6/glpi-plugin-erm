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

use GlpiPlugin\Erm\Review;
use GlpiPlugin\Erm\Risk;

if (isset($_POST['add'])) {
    $risk = new Risk();
    if (!$risk->getFromDB((int) ($_POST['plugin_erm_risks_id'] ?? 0)) || !Review::canReview($risk)) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    $review = new Review();
    if ($review->add($_POST)) {
        Session::addMessageAfterRedirect(__('Review recorded.', 'erm'));
    }
}
Html::back();

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

namespace GlpiPlugin\Erm;

use CommonDBChild;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Indicador clave de riesgo (KRI) de un riesgo corporativo, con umbrales y valores históricos.
 */
class Indicator extends CommonDBChild
{
    public static string $itemtype = Risk::class;
    public static string $items_id = 'plugin_erm_risks_id';
    public static string $rightname = Rights::TREATMENT;
    public static int $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;
    public bool $dohistory = true;

    public static function canView(): bool
    {
        return Risk::canView();
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Indicadores clave de riesgo (KRI)' : 'Indicador clave de riesgo (KRI)';
    }

    public static function getIcon()
    {
        return 'ti ti-gauge';
    }

    public static function frequencies(): array
    {
        return ['monthly' => 'Mensual', 'quarterly' => 'Trimestral', 'weekly' => 'Semanal', 'daily' => 'Diaria'];
    }

    public static function status(?float $value, string $direction, ?float $warn, ?float $crit): string
    {
        if ($value === null) {
            return 'none';
        }
        $worse = fn($v, $t) => $t !== null && ($direction === 'lower_is_worse' ? $v <= $t : $v >= $t);
        return $worse($value, $crit) ? 'red' : ($worse($value, $warn) ? 'orange' : 'green');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Risk && !$item->isNewItem()) {
            $n = countElementsInTable(self::getTable(), ['plugin_erm_risks_id' => $item->getID()]);
            return self::createTabEntry('Indicadores (KRI)', $n, null, self::getIcon());
        }
        return '';
    }

    public static function getForRisk(int $riskId): array
    {
        global $DB;
        $out = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_erm_risks_id' => $riskId], 'ORDER' => 'id']) as $k) {
            $vals = iterator_to_array($DB->request(['FROM' => IndicatorValue::getTable(),
                'WHERE' => ['plugin_erm_indicators_id' => $k['id']], 'ORDER' => ['value_date DESC', 'id DESC'], 'LIMIT' => 12]), false);
            $last = $vals[0] ?? null;
            $warn = $k['threshold_warning'] !== null ? (float) $k['threshold_warning'] : null;
            $crit = $k['threshold_critical'] !== null ? (float) $k['threshold_critical'] : null;
            foreach ($vals as &$v) {
                $v['status'] = self::status((float) $v['value'], $k['direction'], $warn, $crit);
            }
            $k['values'] = array_reverse($vals);
            $k['last'] = $last;
            $k['status'] = $last ? self::status((float) $last['value'], $k['direction'], $warn, $crit) : 'none';
            $k['owner'] = $k['users_id'] ? getUserName($k['users_id']) : '';
            $out[] = $k;
        }
        return $out;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        TemplateRenderer::getInstance()->display('@erm/indicators_tab.html.twig', [
            'risk'        => $item,
            'indicators'  => self::getForRisk($item->getID()),
            'frequencies' => self::frequencies(),
            'can_edit'    => Session::haveRight(self::$rightname, CREATE),
            'action'      => plugin_erm_url('front/indicator.form.php'),
            'today'       => date('Y-m-d'),
        ]);
        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (trim((string) ($input['name'] ?? '')) === '') {
            Session::addMessageAfterRedirect('El indicador debe tener un nombre.', false, ERROR);
            return false;
        }
        if (isset($input['direction']) && !in_array($input['direction'], ['higher_is_worse', 'lower_is_worse'], true)) {
            $input['direction'] = 'higher_is_worse';
        }
        if (isset($input['frequency']) && !array_key_exists($input['frequency'], self::frequencies())) {
            $input['frequency'] = 'monthly';
        }
        return Validator::apply($input, ['threshold_warning' => 'decimal', 'threshold_critical' => 'decimal']);
    }

    public function prepareInputForUpdate($input)
    {
        return Validator::apply($input, ['name' => 'required', 'threshold_warning' => 'decimal', 'threshold_critical' => 'decimal']);
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb([IndicatorValue::class]);
    }
}

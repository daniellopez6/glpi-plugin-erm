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
 * Key risk indicator (KRI) of a corporate risk, with thresholds and history.
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
        return _n('Key risk indicator (KRI)', 'Key risk indicators (KRI)', $nb, 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-gauge';
    }

    public static function frequencies(): array
    {
        return ['monthly' => __('Monthly', 'erm'), 'quarterly' => __('Quarterly', 'erm'), 'weekly' => __('Weekly', 'erm'), 'daily' => __('Daily', 'erm')];
    }

    public static function sources(): array
    {
        return ['manual' => __('Manual entry', 'erm'), 'savedsearch' => __('GLPI saved search (number of results)', 'erm')];
    }

    public static function cronInfo($name)
    {
        return $name === 'ermkri' ? ['description' => __('Measure the automatic KRIs (saved searches) that are due', 'erm')] : [];
    }

    /**
     * Number of results of the saved search of an automatic KRI, executed with the current session.
     */
    public static function measureSavedSearch(int $savedSearchId): ?int
    {
        $search = new \SavedSearch();
        if (!$search->getFromDB($savedSearchId)) {
            return null;
        }
        $data = $search->execute(true);
        return is_array($data) && isset($data['data']['totalcount']) ? (int) $data['data']['totalcount'] : null;
    }

    /** Records today's value of an automatic KRI. */
    public static function recordAutomatic(array $kri): bool
    {
        $count = self::measureSavedSearch((int) $kri['savedsearches_id']);
        if ($count === null) {
            return false;
        }
        $search = new \SavedSearch();
        $search->getFromDB((int) $kri['savedsearches_id']);
        return (bool) (new IndicatorValue())->add([
            'plugin_erm_indicators_id' => $kri['id'],
            'value_date'               => date('Y-m-d'),
            'value'                    => (string) $count,
            'comment'                  => sprintf(__('Automatic: saved search "%s"', 'erm'), $search->getName()),
        ]);
    }

    /**
     * Daily automatic action: measures the automatic KRIs whose next measurement is due. The saved search runs with
     * the rights of its owner (same approach as the GLPI saved search alerts).
     *
     * @param \CronTask $task
     */
    public static function cronErmkri($task): int
    {
        global $DB;
        $today = date('Y-m-d');
        $done = 0;
        $session = $_SESSION;
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['source' => 'savedsearch', 'savedsearches_id' => ['>', 0]]]) as $kri) {
            $last = $DB->request(['SELECT' => ['MAX' => 'value_date AS d'], 'FROM' => IndicatorValue::getTable(),
                                  'WHERE' => ['plugin_erm_indicators_id' => $kri['id']]])->current()['d'] ?? null;
            if (Schedule::nextMeasurement($last, (string) $kri['frequency'], $today) > $today) {
                continue;
            }
            $search = new \SavedSearch();
            $owner = new \User();
            if (!$search->getFromDB((int) $kri['savedsearches_id']) || !$owner->getFromDB((int) $search->fields['users_id'])) {
                continue;
            }
            $auth = new \Auth();
            $auth->user = $owner;
            $auth->auth_succeded = true;
            \Session::init($auth);
            if (self::recordAutomatic($kri)) {
                $done++;
            }
            $_SESSION = $session;
        }
        $task->addVolume($done);
        return $done > 0 ? 1 : 0;
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
            return self::createTabEntry(__('Indicators (KRI)', 'erm'), $n, null, self::getIcon());
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
            $k['search_name'] = $k['source'] === 'savedsearch' && $k['savedsearches_id']
                ? \Dropdown::getDropdownName('glpi_savedsearches', (int) $k['savedsearches_id']) : '';
            $out[] = $k;
        }
        return $out;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Risk) {
            return false;
        }
        TemplateRenderer::getInstance()->display('@erm/indicators_tab.html.twig', [
           'risk'        => $item,
           'indicators'  => self::getForRisk($item->getID()),
           'frequencies' => self::frequencies(),
           'sources'     => self::sources(),
           'search_dropdown' => \SavedSearch::dropdown(['name' => 'savedsearches_id', 'display' => false]),
           'can_edit'    => Session::haveRight(self::$rightname, CREATE),
           'action'      => plugin_erm_url('front/indicator.form.php'),
           'today'       => date('Y-m-d'),
        ]);
        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (trim((string) ($input['name'] ?? '')) === '') {
            Session::addMessageAfterRedirect(__('The indicator must have a name.', 'erm'), false, ERROR);
            return false;
        }
        if (isset($input['direction']) && !in_array($input['direction'], ['higher_is_worse', 'lower_is_worse'], true)) {
            $input['direction'] = 'higher_is_worse';
        }
        if (isset($input['frequency']) && !array_key_exists($input['frequency'], self::frequencies())) {
            $input['frequency'] = 'monthly';
        }
        $input = $this->checkSource($input);
        if ($input === false) {
            return false;
        }
        return Validator::apply($input, ['threshold_warning' => 'decimal', 'threshold_critical' => 'decimal']);
    }

    /**
     * An automatic KRI needs a saved search the user can see.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>|false
     */
    private function checkSource(array $input): array|false
    {
        if (!array_key_exists('source', $input)) {
            return $input;
        }
        if ($input['source'] !== 'savedsearch') {
            $input['source'] = 'manual';
            $input['savedsearches_id'] = 0;
            return $input;
        }
        $search = new \SavedSearch();
        if (!$search->getFromDB((int) ($input['savedsearches_id'] ?? 0)) || !$search->canViewItem()) {
            \Session::addMessageAfterRedirect(__('Select a saved search you can see for an automatic KRI.', 'erm'), false, ERROR);
            return false;
        }
        // The automatic action runs the search with the rights of its owner: only own saved searches can be used,
        // unless the user manages the ERM configuration (otherwise counts outside the user's scope could be exposed)
        if ((int) $search->fields['users_id'] !== (int) \Session::getLoginUserID() && !\Session::haveRight(Rights::CONFIG, UPDATE)) {
            \Session::addMessageAfterRedirect(__('An automatic KRI can only use one of your own saved searches.', 'erm'), false, ERROR);
            return false;
        }
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->checkSource($input);
        if ($input === false) {
            return false;
        }
        return Validator::apply($input, ['name' => 'required', 'threshold_warning' => 'decimal', 'threshold_critical' => 'decimal']);
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb([IndicatorValue::class]);
    }
}

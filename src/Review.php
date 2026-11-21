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
use Log;
use Session;

/**
 * Periodic review of a corporate risk (ISO 31000 6.6): snapshot of the assessment at the review date, conclusion,
 * participants and next review date. The series of reviews shows how the risk evolves.
 */
class Review extends CommonDBChild
{
    public static string $itemtype = Risk::class;
    public static string $items_id = 'plugin_erm_risks_id';
    public static string $rightname = Rights::RISK;
    public static int $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;

    /** Fields copied from the risk at the review date. */
    private const SNAPSHOT = ['inherent_probability', 'inherent_impact', 'inherent_score', 'inherent_level',
                              'residual_probability', 'residual_impact', 'residual_score', 'residual_level',
                              'target_score', 'target_level', 'status', 'treatment'];

    public static function getTypeName($nb = 0)
    {
        return _n('Review', 'Reviews', $nb, 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-history-toggle';
    }

    public static function canView(): bool
    {
        return Risk::canView();
    }

    public static function conclusions(): array
    {
        return [
            'unchanged' => __('Assessment confirmed', 'erm'),
            'updated'   => __('Assessment updated', 'erm'),
            'escalated' => __('Escalated to the Risk Committee', 'erm'),
            'close'     => __('Proposed for closure', 'erm'),
        ];
    }

    /** Recording a review requires being able to update the risk (owner, manager or committee). */
    public static function canReview(Risk $risk): bool
    {
        return !$risk->isNewItem() && $risk->canUpdateItem() && Risk::canUpdate();
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Risk && !$item->isNewItem()) {
            $n = countElementsInTable(self::getTable(), ['plugin_erm_risks_id' => $item->getID()]);
            return self::createTabEntry(self::getTypeName(2), $n, null, self::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $DB;
        if (!$item instanceof Risk) {
            return false;
        }
        $rows = iterator_to_array($DB->request([
            'FROM' => self::getTable(), 'WHERE' => ['plugin_erm_risks_id' => $item->getID()], 'ORDER' => ['review_date', 'id'],
        ]), false);
        $conclusions = self::conclusions();
        foreach ($rows as &$r) {
            $r['reviewer'] = $r['users_id'] ? getUserName($r['users_id']) : '';
            $r['conclusion_label'] = $conclusions[$r['conclusion']] ?? $r['conclusion'];
            $r['inherent_badge'] = Scale::badge($r['inherent_level'], (int) $r['inherent_score'] ?: null);
            $r['residual_badge'] = Scale::badge($r['residual_level'], (int) $r['residual_score'] ?: null);
            $r['target_badge'] = $r['target_level'] ? Scale::badge($r['target_level'], (int) $r['target_score'] ?: null) : '';
        }
        unset($r);

        // Evolution chart: one point per review plus today's assessment
        $points = array_map(fn($r) => ['date' => $r['review_date'], 'inherent' => (int) $r['inherent_score'],
                                       'residual' => (int) $r['residual_score'], 'target' => (int) $r['target_score']], $rows);
        $points[] = ['date' => __('Today', 'erm'), 'inherent' => (int) $item->fields['inherent_score'],
                     'residual' => (int) $item->fields['residual_score'], 'target' => (int) $item->fields['target_score']];

        TemplateRenderer::getInstance()->display('@erm/reviews_tab.html.twig', [
            'risk'        => $item,
            'rows'        => array_reverse($rows),
            'chart'       => count($points) > 1 ? self::chart($points) : null,
            'conclusions' => $conclusions,
            'can_review'  => self::canReview($item),
            'action'      => plugin_erm_url('front/review.form.php'),
            'today'       => date('Y-m-d'),
            'next_default' => date('Y-m-d', strtotime('+12 months')),
        ]);
        return true;
    }

    /**
     * Coordinates of an SVG line chart (score 0-25) for the template.
     *
     * @param list<array{date:string, inherent:int, residual:int, target:int}> $points
     * @return array<string,mixed>
     */
    private static function chart(array $points): array
    {
        $w = 640;
        $h = 180;
        $pad = 30;
        $n = count($points);
        $x = fn(int $i) => $pad + ($n > 1 ? $i * ($w - 2 * $pad) / ($n - 1) : 0);
        $y = fn(int $score) => $h - $pad - $score * ($h - 2 * $pad) / 25;
        $series = [];
        foreach (['inherent' => '#adb5bd', 'residual' => '#f76707', 'target' => '#2fb344'] as $key => $color) {
            $coords = [];
            foreach ($points as $i => $p) {
                if ($p[$key] > 0) {
                    $coords[] = ['x' => round($x($i), 1), 'y' => round($y($p[$key]), 1), 'v' => $p[$key]];
                }
            }
            $series[$key] = ['color' => $color, 'points' => $coords,
                             'path' => implode(' ', array_map(fn($c) => $c['x'] . ',' . $c['y'], $coords))];
        }
        $bands = [];
        foreach ([[1, 4, 'low'], [5, 9, 'medium'], [10, 16, 'high'], [20, 25, 'critical']] as [$from, $to, $lvl]) {
            $bands[] = ['y' => round($y($to), 1), 'h' => round($y($from - 1) - $y($to), 1), 'color' => Scale::color($lvl)];
        }
        return ['w' => $w, 'h' => $h, 'pad' => $pad, 'series' => $series, 'bands' => $bands,
                'labels' => array_map(fn($i, $p) => ['x' => round($x($i), 1), 'text' => $p['date']], array_keys($points), $points)];
    }

    public function prepareInputForAdd($input)
    {
        $risk = new Risk();
        if (!$risk->getFromDB((int) ($input['plugin_erm_risks_id'] ?? 0)) || !self::canReview($risk)) {
            return false;
        }
        $input = Validator::apply($input, ['review_date' => 'date', 'next_review_date' => 'date']);
        if ($input === false) {
            return false;
        }
        if (($input['review_date'] ?? 'NULL') === 'NULL') {
            $input['review_date'] = date('Y-m-d');
        }
        if (trim((string) ($input['comment'] ?? '')) === '') {
            Session::addMessageAfterRedirect(__('Describe the conclusion of the review.', 'erm'), false, ERROR);
            return false;
        }
        if (!array_key_exists($input['conclusion'] ?? '', self::conclusions())) {
            $input['conclusion'] = 'unchanged';
        }
        foreach (self::SNAPSHOT as $field) {
            $input[$field] = $risk->fields[$field];
        }
        $input['users_id'] = (int) Session::getLoginUserID();
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        // Reviews are evidence: they cannot be modified
        return false;
    }

    public function post_addItem()
    {
        global $DB;
        // The risk records its last and next review dates
        $values = ['last_review_date' => $this->fields['review_date']];
        if (!empty($this->fields['next_review_date'])) {
            $values['review_date'] = $this->fields['next_review_date'];
        }
        $DB->update(Risk::getTable(), $values, ['id' => $this->fields['plugin_erm_risks_id']]);
        Log::history((int) $this->fields['plugin_erm_risks_id'], Risk::class, [0, '', sprintf(
            __('Review recorded: %s', 'erm'),
            self::conclusions()[$this->fields['conclusion']] ?? ''
        )], '', Log::HISTORY_LOG_SIMPLE_MESSAGE);
        parent::post_addItem();
    }
}

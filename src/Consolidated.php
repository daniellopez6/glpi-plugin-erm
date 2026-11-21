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

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Plugin;
use Session;

/**
 * Integrated risk map: corporate risks (ERM) together with the information security (ISMS) and supplier risks of
 * GLPI GRC Manager, consolidated by level, and coherence check between a corporate risk and its linked risks.
 * GRC Manager data is only read, and only when the plugin is active and the user may see it.
 */
class Consolidated extends CommonGLPI
{
    public const GRC_RISK = 'GlpiPlugin\\Grcmanager\\Risk';
    public const GRC_SUPPLIER_RISK = 'GlpiPlugin\\Grcmanager\\SupplierRisk';

    public static function getTypeName($nb = 0)
    {
        return __('Integrated risk map', 'erm');
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    public static function getIcon()
    {
        return 'ti ti-stack-2';
    }

    public static function canView(): bool
    {
        return Risk::canView();
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/consolidated.php', $full);
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        return ['title' => self::getMenuName(), 'page' => self::getSearchURL(false), 'icon' => self::getIcon()];
    }

    /** Optional GRC Manager classes are resolved at runtime (they are not known to static analysis). */
    private static function hasClass(string $class): bool
    {
        return class_exists($class);
    }

    /** GRC Manager is installed, active and readable by the current user. */
    public static function grcAvailable(): bool
    {
        global $DB;
        return Plugin::isPluginActive('grcmanager') && $DB->tableExists('glpi_plugin_grcmanager_risks')
            && Session::haveRight('plugin_grcmanager', READ);
    }

    /**
     * ISMS and supplier risks of GRC Manager in the visible entities.
     *
     * @return array{isms: list<array<string,mixed>>, suppliers: list<array<string,mixed>>}
     */
    public static function grcRisks(): array
    {
        global $DB;
        $out = ['isms' => [], 'suppliers' => []];
        if (!self::grcAvailable()) {
            return $out;
        }
        $sources = ['isms' => ['glpi_plugin_grcmanager_risks', self::GRC_RISK],
                    'suppliers' => ['glpi_plugin_grcmanager_supplierrisks', self::GRC_SUPPLIER_RISK]];
        foreach ($sources as $key => [$table, $itemtype]) {
            if (!$DB->tableExists($table)) {
                continue;
            }
            foreach ($DB->request(['FROM' => $table, 'WHERE' => getEntitiesRestrictCriteria($table, '', '', true)]) as $r) {
                $out[$key][] = [
                    'id'       => (int) $r['id'],
                    'title'    => (string) $r['title'],
                    'level'    => Consolidation::grcLevel($r),
                    'owner'    => !empty($r['users_id']) ? getUserName((int) $r['users_id']) : '',
                    'entity'   => \Dropdown::getDropdownName('glpi_entities', (int) $r['entities_id']),
                    'url'      => self::hasClass($itemtype) ? $itemtype::getFormURLWithID((int) $r['id']) : '',
                ];
            }
        }
        return $out;
    }

    /**
     * Data of the integrated map (also used by the Board report).
     *
     * @return array<string,mixed>
     */
    public static function data(): array
    {
        global $DB;
        $erm = Risk::activeRisks();
        $grc = self::grcRisks();
        $sources = [
            'erm'       => ['label' => __('Corporate risks (ERM)', 'erm'), 'rows' => []],
            'isms'      => ['label' => __('Information security risks (ISMS)', 'erm'), 'rows' => $grc['isms']],
            'suppliers' => ['label' => __('Supplier risks', 'erm'), 'rows' => $grc['suppliers']],
        ];
        foreach ($erm as $r) {
            $sources['erm']['rows'][] = ['id' => (int) $r['id'], 'title' => $r['code'] . ' ' . $r['name'], 'level' => DrillFilter::level($r),
                                        'owner' => $r['users_id'] ? getUserName((int) $r['users_id']) : '',
                                        'entity' => \Dropdown::getDropdownName('glpi_entities', (int) $r['entities_id']),
                                        'url' => Risk::getFormURLWithID((int) $r['id'])];
        }
        $counts = [];
        $top = [];
        foreach ($sources as $key => $src) {
            $counts[$key] = array_fill_keys(Scale::LEVELS, 0) + ['total' => count($src['rows'])];
            foreach ($src['rows'] as $row) {
                if (isset($counts[$key][$row['level']])) {
                    $counts[$key][$row['level']]++;
                }
                if (in_array($row['level'], ['high', 'critical'], true)) {
                    $top[] = $row + ['source' => $src['label'], 'source_key' => $key];
                }
            }
        }
        usort($top, fn($a, $b) => Scale::rank($b['level']) <=> Scale::rank($a['level']) ?: strcmp($a['title'], $b['title']));

        // Coherence: corporate risks assessed below the ISMS / supplier risks linked to them
        $incoherent = [];
        if ($erm && ($grc['isms'] || $grc['suppliers'])) {
            $levels = ['isms' => array_column($grc['isms'], null, 'id'), 'suppliers' => array_column($grc['suppliers'], null, 'id')];
            $links = [];
            $iter = $DB->request(['FROM' => Risk_Item::getTable(), 'WHERE' => [
                'plugin_erm_risks_id' => array_column($erm, 'id'), 'itemtype' => [self::GRC_RISK, self::GRC_SUPPLIER_RISK],
            ]]);
            foreach ($iter as $l) {
                $key = $l['itemtype'] === self::GRC_RISK ? 'isms' : 'suppliers';
                if (isset($levels[$key][(int) $l['items_id']])) {
                    $links[(int) $l['plugin_erm_risks_id']][] = $levels[$key][(int) $l['items_id']];
                }
            }
            foreach ($erm as $r) {
                $linked = $links[(int) $r['id']] ?? [];
                if (Consolidation::belowLinked(DrillFilter::level($r), array_column($linked, 'level'))) {
                    $incoherent[] = ['risk' => $r, 'level' => DrillFilter::level($r),
                                     'highest' => Consolidation::highest(array_column($linked, 'level')), 'linked' => $linked];
                }
            }
        }
        return ['sources' => $sources, 'counts' => $counts, 'top' => $top, 'incoherent' => $incoherent,
                'grc' => self::grcAvailable(), 'grc_installed' => Plugin::isPluginActive('grcmanager')];
    }

    public static function show(): void
    {
        $data = self::data();
        foreach ($data['incoherent'] as &$i) {
            $i['level_badge'] = Scale::badge($i['level']);
            $i['highest_badge'] = Scale::badge($i['highest']);
        }
        unset($i);
        foreach ($data['top'] as &$t) {
            $t['badge'] = Scale::badge($t['level']);
        }
        unset($t);
        TemplateRenderer::getInstance()->display('@erm/consolidated.html.twig', $data + [
            'levels'      => Scale::levels(),
            'level_color' => array_combine(Scale::LEVELS, array_map([Scale::class, 'color'], Scale::LEVELS)),
            'drill_url'   => Dashboard::drillURL(),
            'risk_url'    => Risk::getFormURL(),
            'grc_urls'    => [
                'isms'      => self::hasClass(self::GRC_RISK) ? (self::GRC_RISK)::getSearchURL() : '',
                'suppliers' => self::hasClass(self::GRC_SUPPLIER_RISK) ? (self::GRC_SUPPLIER_RISK)::getSearchURL() : '',
            ],
        ]);
    }
}

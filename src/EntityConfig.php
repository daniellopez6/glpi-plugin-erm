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

use CommonDBTM;
use CommonGLPI;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Per entity ERM settings: risk code prefix and appetite/tolerance overrides by category.
 * Displayed as a tab on the entity form. Values are inherited from parent entities.
 */
class EntityConfig extends CommonDBTM
{
    public static string $rightname = Rights::CONFIG;

    /** @var array<int, array<int, array<string,string>>>|null overrides by category then entity */
    private static ?array $overrides = null;
    /** @var array<int, array{parent:int, appetite_level:string, tolerance_level:string}>|null */
    private static ?array $categories = null;
    /** @var array<int, array<int,float|null>> impact thresholds by entity */
    private static array $thresholds = [];

    /** Impact levels that have a minimum amount (level 1 "Insignificant" starts at 0). */
    public const THRESHOLD_LEVELS = [2, 3, 4, 5];

    public static function getTypeName($nb = 0)
    {
        return __('ERM settings', 'erm');
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Entity && self::canView()) {
            return self::createTabEntry(__('Corporate risks (ERM)', 'erm'), 0, null, Risk::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Entity) {
            return false;
        }
        $entityId = (int) $item->getID();
        $rows = [];
        foreach (self::categories() as $id => $cat) {
            if ($cat['parent'] !== 0) {
                continue;
            }
            $own = self::overrides()[$id][$entityId] ?? [];
            [$effApp, $effTol] = Category::effectiveAppetite($id, $entityId);
            $rows[] = [
                'id'                => $id,
                'name'              => $cat['name'],
                'default_appetite'  => $cat['appetite_level'],
                'default_tolerance' => $cat['tolerance_level'],
                'appetite'          => $own['appetite_level'] ?? '',
                'tolerance'         => $own['tolerance_level'] ?? '',
                'statement'         => $own['appetite_statement'] ?? '',
                'eff_appetite'      => Scale::badge($effApp ?: null),
                'eff_tolerance'     => Scale::badge($effTol ?: null),
            ];
        }
        $own = self::ownThresholds($entityId);
        $effective = self::impactThresholds($entityId);
        $thresholds = [];
        foreach (self::THRESHOLD_LEVELS as $lvl) {
            $thresholds[] = ['level' => $lvl, 'label' => Scale::impacts()[$lvl], 'value' => $own[$lvl],
                             'effective' => $effective[$lvl] !== null ? Scale::money($effective[$lvl]) : ''];
        }
        TemplateRenderer::getInstance()->display('@erm/entity_config.html.twig', [
            'thresholds' => $thresholds,
            'inherited_thresholds' => !array_filter($own, fn($v) => $v !== null) && array_filter($effective, fn($v) => $v !== null),
            'entity_id'  => $entityId,
            'prefix'     => self::ownPrefix($entityId),
            'eff_prefix' => self::prefixFor($entityId),
            'rows'       => $rows,
            'levels'     => Scale::levels(),
            'can_edit'   => Session::haveRight(self::$rightname, UPDATE),
            'action'     => plugin_erm_url('front/entityconfig.form.php'),
        ]);
        return true;
    }

    /**
     * Entity followed by its ancestors, closest first.
     *
     * @return list<int>
     */
    public static function entityChain(int $entityId): array
    {
        $chain = [];
        $entity = new Entity();
        $guard = 0;
        while ($entityId >= 0 && $guard++ < 30 && !in_array($entityId, $chain, true)) {
            $chain[] = $entityId;
            if ($entityId === 0 || !$entity->getFromDB($entityId)) {
                break;
            }
            $entityId = (int) $entity->fields['entities_id'];
        }
        return $chain;
    }

    /**
     * Category followed by its ancestors, closest first.
     *
     * @return list<int>
     */
    public static function categoryChain(int $categoryId): array
    {
        $chain = [];
        $cats = self::categories();
        while ($categoryId > 0 && isset($cats[$categoryId]) && !in_array($categoryId, $chain, true)) {
            $chain[] = $categoryId;
            $categoryId = $cats[$categoryId]['parent'];
        }
        return $chain;
    }

    /**
     * Categories indexed by id.
     *
     * @return array<int, array{name:string, parent:int, appetite_level:string, tolerance_level:string}>
     */
    public static function categories(): array
    {
        global $DB;
        if (self::$categories === null) {
            self::$categories = [];
            foreach ($DB->request(['FROM' => Category::getTable(), 'ORDER' => 'completename']) as $row) {
                self::$categories[(int) $row['id']] = [
                    'name'            => (string) $row['completename'],
                    'parent'          => (int) $row['plugin_erm_categories_id'],
                    'appetite_level'  => (string) $row['appetite_level'],
                    'tolerance_level' => (string) $row['tolerance_level'],
                ];
            }
        }
        return self::$categories;
    }

    /**
     * Appetite overrides indexed by category then entity.
     *
     * @return array<int, array<int, array<string,string>>>
     */
    public static function overrides(): array
    {
        global $DB;
        if (self::$overrides === null) {
            self::$overrides = [];
            foreach ($DB->request(['FROM' => 'glpi_plugin_erm_appetites']) as $row) {
                self::$overrides[(int) $row['plugin_erm_categories_id']][(int) $row['entities_id']] = [
                    'appetite_level'     => (string) $row['appetite_level'],
                    'tolerance_level'    => (string) $row['tolerance_level'],
                    'appetite_statement' => (string) $row['appetite_statement'],
                ];
            }
        }
        return self::$overrides;
    }

    /** Clears the request caches (after saving). */
    public static function resetCache(): void
    {
        self::$overrides = null;
        self::$categories = null;
        self::$thresholds = [];
    }

    /**
     * Minimum amounts of impact levels 2-5 defined on the entity itself.
     *
     * @return array<int,float|null>
     */
    public static function ownThresholds(int $entityId): array
    {
        $config = new self();
        $found = $config->getFromDBByCrit(['entities_id' => $entityId]);
        $out = [];
        foreach (self::THRESHOLD_LEVELS as $lvl) {
            $v = $found ? ($config->fields["impact_{$lvl}"] ?? null) : null;
            $out[$lvl] = $v === null || $v === '' ? null : (float) $v;
        }
        return $out;
    }

    /**
     * Economic impact criteria of an entity: its own, or those of the closest parent defining any.
     *
     * @return array<int,float|null>
     */
    public static function impactThresholds(int $entityId): array
    {
        if (!isset(self::$thresholds[$entityId])) {
            $result = array_fill_keys(self::THRESHOLD_LEVELS, null);
            foreach (self::entityChain($entityId) as $id) {
                $own = self::ownThresholds($id);
                if (array_filter($own, fn($v) => $v !== null)) {
                    $result = $own;
                    break;
                }
            }
            self::$thresholds[$entityId] = $result;
        }
        return self::$thresholds[$entityId];
    }

    /** Prefix defined on the entity itself ('' when inherited). */
    public static function ownPrefix(int $entityId): string
    {
        $config = new self();
        return $config->getFromDBByCrit(['entities_id' => $entityId]) ? (string) $config->fields['code_prefix'] : '';
    }

    /** Effective prefix: the entity's one, or the closest parent's one, or the default. */
    public static function prefixFor(int $entityId): string
    {
        foreach (self::entityChain($entityId) as $id) {
            $prefix = self::ownPrefix($id);
            if ($prefix !== '') {
                return Numbering::normalizePrefix($prefix);
            }
        }
        return Numbering::DEFAULT_PREFIX;
    }

    /**
     * Saves the prefix and appetite overrides of an entity from the tab form.
     *
     * @param array<string,mixed> $post
     */
    public static function saveFromForm(int $entityId, array $post): void
    {
        global $DB;
        $prefix = trim((string) ($post['code_prefix'] ?? ''));
        $prefix = $prefix === '' ? '' : Numbering::normalizePrefix($prefix);
        $values = ['code_prefix' => $prefix];
        $previous = null;
        foreach (self::THRESHOLD_LEVELS as $lvl) {
            $raw = trim((string) ($post['impact'][$lvl] ?? ''));
            $amount = $raw === '' ? 'NULL' : Validator::decimal($raw);
            if ($amount === false || ($amount !== 'NULL' && (float) $amount < 0)) {
                Session::addMessageAfterRedirect(sprintf(__('Invalid amount for impact "%s".', 'erm'), Scale::impacts()[$lvl]), false, ERROR);
                return;
            }
            if ($amount !== 'NULL') {
                if ($previous !== null && (float) $amount <= $previous) {
                    Session::addMessageAfterRedirect(__('The minimum amounts must increase with the impact level.', 'erm'), false, ERROR);
                    return;
                }
                $previous = (float) $amount;
            }
            $values["impact_{$lvl}"] = $amount;
        }
        $config = new self();
        if ($config->getFromDBByCrit(['entities_id' => $entityId])) {
            $config->update(['id' => $config->getID()] + $values);
        } elseif ($prefix !== '' || array_filter($values, fn($v) => $v !== 'NULL' && $v !== '')) {
            $config->add(['entities_id' => $entityId] + $values);
        }
        $levels = array_keys(Scale::levels());
        foreach ((array) ($post['appetite'] ?? []) as $categoryId => $values) {
            $categoryId = (int) $categoryId;
            if (!isset(self::categories()[$categoryId]) || !is_array($values)) {
                continue;
            }
            $app = in_array($values['appetite_level'] ?? '', $levels, true) ? $values['appetite_level'] : '';
            $tol = in_array($values['tolerance_level'] ?? '', $levels, true) ? $values['tolerance_level'] : '';
            $stmt = trim((string) ($values['appetite_statement'] ?? ''));
            $where = ['entities_id' => $entityId, 'plugin_erm_categories_id' => $categoryId];
            $DB->delete('glpi_plugin_erm_appetites', $where);
            if ($app !== '' || $tol !== '' || $stmt !== '') {
                $DB->insert('glpi_plugin_erm_appetites', $where + [
                    'appetite_level' => $app, 'tolerance_level' => $tol, 'appetite_statement' => $stmt,
                    'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
                ]);
            }
        }
        self::resetCache();
    }
}

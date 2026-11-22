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
use Document_Item;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Log;
use Session;

/**
 * Corporate risk (ERM): 5x5 inherent and residual assessment, economic impact, appetite and treatment.
 */
class Risk extends CommonDBTM
{
    public static string $rightname = 'plugin_erm';
    public bool $dohistory = true;
    protected bool $usenotepad = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Corporate risk', 'Corporate risks', $nb, 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-building-bank';
    }

    public static function getMenuName()
    {
        return __('Corporate risk register', 'erm');
    }

    // ------------------------------------------------------------------ permisos
    public static function canView(): bool
    {
        return Rights::canReadSome();
    }

    public static function canUpdate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [UPDATE, UPDATE_OWNED]) || Rights::canAccept();
    }

    /** Whether the user can edit the whole assessment (not only the acceptance decision). */
    public function canEditAssessment(): bool
    {
        if (Session::haveRight(self::$rightname, UPDATE)) {
            return Rights::canReadAll() || Rights::isOwn($this->fields);
        }
        return Session::haveRight(self::$rightname, UPDATE_OWNED) && Rights::isOwn($this->fields);
    }

    /** Fields that a user with only the acceptance right can update. */
    private const ACCEPTANCE_FIELDS = ['id', 'status', 'treatment', 'justification', 'review_date', 'last_review_date', 'acceptance_valid_until'];

    public function canViewItem(): bool
    {
        if (!parent::canViewItem()) {
            return false;
        }
        return Rights::canReadAll() || (Session::haveRight(self::$rightname, READ_OWNED) && Rights::isOwn($this->fields));
    }

    public function canUpdateItem(): bool
    {
        if (!parent::canUpdateItem()) {
            return false;
        }
        return $this->canEditAssessment() || (Rights::canAccept() && $this->canViewItem());
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(Review::class, $tabs, $options);
        $this->addStandardTab(Action::class, $tabs, $options);
        $this->addStandardTab(Indicator::class, $tabs, $options);
        $this->addStandardTab(Event_Item::class, $tabs, $options);
        $this->addStandardTab(Risk_Item::class, $tabs, $options);
        $this->addStandardTab(Document_Item::class, $tabs, $options);
        $this->addStandardTab('Notepad', $tabs, $options);
        $this->addStandardTab(Log::class, $tabs, $options);
        return $tabs;
    }

    // ------------------------------------------------------------------ computation
    private function compute(array $input): array|false
    {
        foreach (['inherent_probability', 'inherent_impact', 'residual_probability', 'residual_impact', 'target_probability', 'target_impact'] as $f) {
            if (array_key_exists($f, $input)) {
                $input[$f] = Validator::range($input[$f], 0, 5);
            }
        }
        foreach (['velocity' => Scale::velocities(), 'trend' => Scale::trends(), 'control_effectiveness' => Scale::effectiveness()] as $f => $allowed) {
            if (array_key_exists($f, $input) && !array_key_exists($input[$f], $allowed)) {
                $input[$f] = '';
            }
        }
        $merged = $input + $this->fields;
        foreach (['inherent', 'residual', 'target'] as $kind) {
            $p = (int) ($merged["{$kind}_probability"] ?? 0);
            $i = (int) ($merged["{$kind}_impact"] ?? 0);
            if (array_key_exists("{$kind}_probability", $input) || array_key_exists("{$kind}_impact", $input)) {
                $input["{$kind}_score"] = ($p > 0 && $i > 0) ? $p * $i : 0;
                $input["{$kind}_level"] = Scale::levelFor($p, $i);
            }
        }
        $input = Validator::apply($input, ['economic_impact_min' => 'decimal', 'economic_impact_max' => 'decimal',
                                          'review_date' => 'date', 'last_review_date' => 'date', 'target_date' => 'date']);
        if ($input === false) {
            return false;
        }
        if (array_key_exists('treatment', $input) && !array_key_exists($input['treatment'], Scale::treatments())) {
            $input['treatment'] = '';
        }
        if (array_key_exists('status', $input) && !array_key_exists($input['status'], Scale::statuses())) {
            $input['status'] = 'identified';
        }
        return $input;
    }

    public function prepareInputForAdd($input)
    {
        if (trim((string) ($input['name'] ?? '')) === '') {
            Session::addMessageAfterRedirect(__('The risk must have a name.', 'erm'), false, ERROR);
            return false;
        }
        $input = $this->compute($input);
        if ($input === false) {
            return false;
        }
        $entityId = (int) ($input['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0));
        if (trim((string) ($input['code'] ?? '')) === '') {
            $input['code'] = Numbering::next(self::codesOfEntity($entityId), EntityConfig::prefixFor($entityId));
        } elseif (!$this->isCodeAvailable((string) $input['code'], $entityId)) {
            return false;
        }
        $input = $this->applyAcceptance($input, ['status' => '', 'users_id' => 0, 'treatment' => '']);
        return $input;
    }

    /**
     * Formal acceptance (segregation of duties): only a profile with the "Accept risks" right can
     * accept or revoke the acceptance, never the risk owner. Who and when are recorded automatically.
     */
    private function applyAcceptance(array|false $input, array $current): array|false
    {
        if ($input === false) {
            return false;
        }
        $wasAccepted = ($current['status'] ?? '') === 'accepted';
        $willAccept  = ($input['status'] ?? $current['status'] ?? '') === 'accepted';
        $treatment   = $input['treatment'] ?? $current['treatment'] ?? '';
        $owner       = (int) ($input['users_id'] ?? $current['users_id'] ?? 0);

       // Acceptance fields are never taken from the request.
        unset($input['users_id_acceptance'], $input['date_acceptance']);
        $internal = Session::getLoginUserID() === false;
        $today = date('Y-m-d');

        // Validity of the acceptance: only the acceptance right can change it, and a new date must be in the future
        if (array_key_exists('acceptance_valid_until', $input)) {
            $until = Validator::date((string) $input['acceptance_valid_until']);
            $currentUntil = $current['acceptance_valid_until'] ?? null;
            if (!$willAccept || (!$internal && !Rights::canAccept()) || $until === 'NULL' || $until === $currentUntil) {
                unset($input['acceptance_valid_until']);
            } elseif ($until === false || $until <= $today) {
                Session::addMessageAfterRedirect(__('The acceptance must be valid until a future date.', 'erm'), false, ERROR);
                return false;
            } else {
                $input['acceptance_valid_until'] = $until;
            }
        }

        if ($willAccept === $wasAccepted) {
            // Re-acceptance: extending the validity of an accepted risk records who and when, like an acceptance
            if (
                $willAccept && !$internal && isset($input['acceptance_valid_until'])
                && $input['acceptance_valid_until'] !== ($current['acceptance_valid_until'] ?? null)
            ) {
                if ($owner === (int) Session::getLoginUserID()) {
                    Session::addMessageAfterRedirect(__('The risk owner cannot accept their own risk (segregation of duties).', 'erm'), false, ERROR);
                    return false;
                }
                $input['users_id_acceptance'] = (int) Session::getLoginUserID();
                $input['date_acceptance'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
            }
            return $input;
        }
        if ($internal) {
            if ($willAccept && empty($input['acceptance_valid_until'])) {
                $input['acceptance_valid_until'] = AcceptanceState::defaultUntil($today);
            }
            return $input; // internal processes (installation, CLI)
        }
        if (!Rights::canAccept()) {
            Session::addMessageAfterRedirect($willAccept
              ? __('You are not allowed to accept risks: this is done by the Risk Committee or the Board.', 'erm')
              : __('You are not allowed to revoke the acceptance of a risk.', 'erm'), false, ERROR);
            return false;
        }
        if ($willAccept && $treatment !== 'accept') {
            Session::addMessageAfterRedirect(__('To accept the risk, the treatment decision must be "Accept".', 'erm'), false, ERROR);
            return false;
        }
        if ($willAccept && $owner === (int) Session::getLoginUserID()) {
            Session::addMessageAfterRedirect(__('The risk owner cannot accept their own risk (segregation of duties).', 'erm'), false, ERROR);
            return false;
        }
        if ($willAccept) {
            $input['users_id_acceptance'] = (int) Session::getLoginUserID();
            $input['date_acceptance'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
            if (empty($input['acceptance_valid_until'])) {
                $input['acceptance_valid_until'] = AcceptanceState::defaultUntil($today);
            }
        } else {
            $input['users_id_acceptance'] = 0;
            $input['date_acceptance'] = 'NULL';
            $input['acceptance_valid_until'] = 'NULL';
        }
        return $input;
    }

    /**
     * Codes already used in an entity.
     *
     * @return list<string>
     */
    public static function codesOfEntity(int $entityId): array
    {
        global $DB;
        $codes = [];
        foreach ($DB->request(['SELECT' => 'code', 'FROM' => self::getTable(), 'WHERE' => ['entities_id' => $entityId]]) as $row) {
            $codes[] = (string) $row['code'];
        }
        return $codes;
    }

    /** A risk code must be unique within its entity. */
    private function isCodeAvailable(string $code, int $entityId): bool
    {
        $where = ['code' => trim($code), 'entities_id' => $entityId];
        if (!$this->isNewItem()) {
            $where['NOT'] = ['id' => $this->getID()];
        }
        if (countElementsInTable(self::getTable(), $where) > 0) {
            Session::addMessageAfterRedirect(sprintf(__('The code %s is already used in this entity.', 'erm'), htmlescape($code)), false, ERROR);
            return false;
        }
        return true;
    }

    public function prepareInputForUpdate($input)
    {
        // Profile with only the "Accept risks" right: changes outside the treatment decision are discarded.
        if (Session::getLoginUserID() !== false && !$this->canEditAssessment()) {
            $input = array_intersect_key($input, array_flip(self::ACCEPTANCE_FIELDS))
              + array_filter($input, fn($k) => str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
        }
        if (!Rights::entityChangeAllowed($input, $this->fields)) {
            return false;
        }
        $input = $this->compute($input);
        $input = $this->applyAcceptance($input, $this->fields);
        if ($input === false) {
            return false;
        }
        if (isset($input['code']) && trim((string) $input['code']) !== (string) $this->fields['code']) {
            $entityId = (int) ($input['entities_id'] ?? $this->fields['entities_id']);
            if (trim((string) $input['code']) === '') {
                unset($input['code']);
            } elseif (!$this->isCodeAvailable((string) $input['code'], $entityId)) {
                return false;
            }
        }
        $treatment = $input['treatment'] ?? $this->fields['treatment'];
        if (
            ($input['status'] ?? '') === 'closed' && in_array($treatment, ['mitigate', 'transfer'], true)
            && countElementsInTable(Action::getTable(), ['plugin_erm_risks_id' => $this->getID()]) === 0
        ) {
            Session::addMessageAfterRedirect(__('A mitigated or transferred risk cannot be closed without a treatment plan.', 'erm'), false, ERROR);
            return false;
        }
        return $input;
    }

    public function post_addItem()
    {
        Notifier::ownerAssigned($this);
        parent::post_addItem();
    }

    public function post_updateItem($history = true)
    {
        global $DB;
        if (in_array('users_id', $this->updates, true)) {
            Notifier::ownerAssigned($this);
        }
        // Keep the entity of the treatment actions aligned with their risk
        if (array_intersect($this->updates, ['entities_id', 'is_recursive'])) {
            $DB->update(Action::getTable(), [
                'entities_id'  => $this->fields['entities_id'],
                'is_recursive' => $this->fields['is_recursive'],
            ], ['plugin_erm_risks_id' => $this->getID()]);
        }
        parent::post_updateItem($history);
    }

    public function cleanDBonPurge()
    {
        global $DB;
        $this->deleteChildrenAndRelationsFromDb([Review::class, Action::class, Indicator::class]);
        // Link tables are not GLPI relations (CommonDBConnexity): clean them explicitly
        $DB->delete(Risk_Item::getTable(), ['plugin_erm_risks_id' => $this->getID()]);
        $DB->delete(Event_Item::getTable(), ['itemtype' => self::class, 'items_id' => $this->getID()]);
    }

    /** @return array{0:string,1:string} */
    public function appetitePosition(): array
    {
        [$app, $tol] = Category::effectiveAppetite((int) $this->fields['plugin_erm_categories_id'], (int) $this->fields['entities_id']);
        $level = $this->fields['residual_level'] ?: $this->fields['inherent_level'];
        return Scale::appetitePosition($level, $app, $tol);
    }

    // ------------------------------------------------------------------ search
    public function rawSearchOptions()
    {
        $t = $this->getTable();
        $tab = [['id' => 'common', 'name' => self::getTypeName(1)]];
        $tab[] = ['id' => 1, 'table' => $t, 'field' => 'name', 'name' => __('Risk', 'erm'), 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 2, 'table' => $t, 'field' => 'id', 'name' => __('ID'), 'datatype' => 'number', 'massiveaction' => false];
        $tab[] = ['id' => 3, 'table' => $t, 'field' => 'code', 'name' => __('Code', 'erm'), 'datatype' => 'string'];
        $tab[] = ['id' => 4, 'table' => Category::getTable(), 'field' => 'completename', 'name' => __('Category', 'erm'), 'datatype' => 'dropdown'];
        $tab[] = ['id' => 5, 'table' => 'glpi_users', 'field' => 'name', 'name' => __('Risk owner', 'erm'), 'datatype' => 'dropdown', 'right' => 'all'];
        $tab[] = ['id' => 6, 'table' => 'glpi_groups', 'field' => 'completename', 'name' => __('Group', 'erm'), 'datatype' => 'dropdown'];
        $tab[] = ['id' => 7, 'table' => $t, 'field' => 'inherent_level', 'name' => __('Inherent level', 'erm'), 'datatype' => 'specific', 'additionalfields' => ['inherent_score']];
        $tab[] = ['id' => 8, 'table' => $t, 'field' => 'residual_level', 'name' => __('Residual level', 'erm'), 'datatype' => 'specific', 'additionalfields' => ['residual_score']];
        $tab[] = ['id' => 9, 'table' => $t, 'field' => 'inherent_score', 'name' => __('Inherent score', 'erm'), 'datatype' => 'integer'];
        $tab[] = ['id' => 10, 'table' => $t, 'field' => 'residual_score', 'name' => __('Residual score', 'erm'), 'datatype' => 'integer'];
        $tab[] = ['id' => 11, 'table' => $t, 'field' => 'treatment', 'name' => __('Treatment', 'erm'), 'datatype' => 'specific'];
        $tab[] = ['id' => 12, 'table' => $t, 'field' => 'status', 'name' => __('Status', 'erm'), 'datatype' => 'specific'];
        $tab[] = ['id' => 13, 'table' => $t, 'field' => 'economic_impact_max', 'name' => __('Maximum economic impact', 'erm'), 'datatype' => 'decimal'];
        $tab[] = ['id' => 14, 'table' => $t, 'field' => 'economic_impact_min', 'name' => __('Minimum economic impact', 'erm'), 'datatype' => 'decimal'];
        $tab[] = ['id' => 15, 'table' => $t, 'field' => 'velocity', 'name' => __('Velocity', 'erm'), 'datatype' => 'specific'];
        $tab[] = ['id' => 16, 'table' => $t, 'field' => 'trend', 'name' => __('Trend', 'erm'), 'datatype' => 'specific'];
        $tab[] = ['id' => 17, 'table' => $t, 'field' => 'review_date', 'name' => __('Next review', 'erm'), 'datatype' => 'date'];
        $tab[] = ['id' => 18, 'table' => $t, 'field' => 'control_effectiveness', 'name' => __('Control effectiveness', 'erm'), 'datatype' => 'specific'];
        $tab[] = ['id' => 19, 'table' => $t, 'field' => 'description', 'name' => __('Description', 'erm'), 'datatype' => 'text'];
        $tab[] = ['id' => 20, 'table' => $t, 'field' => 'date_mod', 'name' => __('Last update'), 'datatype' => 'datetime', 'massiveaction' => false];
        $tab[] = ['id' => 80, 'table' => 'glpi_entities', 'field' => 'completename', 'name' => \Entity::getTypeName(1), 'datatype' => 'dropdown'];
        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        return match ($field) {
            'inherent_level' => Scale::badge($values[$field], (int) ($values['inherent_score'] ?? 0) ?: null),
            'residual_level' => Scale::badge($values[$field], (int) ($values['residual_score'] ?? 0) ?: null),
            'treatment'      => htmlescape(Scale::treatments()[$values[$field]] ?? $values[$field]),
            'status'         => htmlescape(Scale::statuses()[$values[$field]] ?? $values[$field]),
            'velocity'       => htmlescape(Scale::velocities()[$values[$field]] ?? $values[$field]),
            'trend'          => htmlescape(Scale::trends()[$values[$field]] ?? $values[$field]),
            'control_effectiveness' => htmlescape(Scale::effectiveness()[$values[$field]] ?? $values[$field]),
            default          => parent::getSpecificValueToDisplay($field, $values, $options),
        };
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $map = ['inherent_level' => Scale::levels(), 'residual_level' => Scale::levels(), 'treatment' => Scale::treatments(),
               'status' => Scale::statuses(), 'velocity' => Scale::velocities(), 'trend' => Scale::trends(),
               'control_effectiveness' => Scale::effectiveness()];
        if (isset($map[$field])) {
            return Dropdown::showFromArray($name, $map[$field], ['display' => false, 'value' => $values[$field] ?? '']);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    // ------------------------------------------------------------------ formulario
    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $pos = $this->isNewItem() ? ['undefined', ''] : $this->appetitePosition();
        $statuses = Scale::statuses();
        if (!Rights::canAccept() && ($this->fields['status'] ?? '') !== 'accepted') {
            unset($statuses['accepted']);
        }
        $acceptance = __('Pending', 'erm');
        if (!empty($this->fields['users_id_acceptance'])) {
            $acceptance = sprintf(
                '%s - %s',
                htmlescape(getUserName((int) $this->fields['users_id_acceptance'])),
                htmlescape(\Html::convDateTime($this->fields['date_acceptance']))
            );
        }
        TemplateRenderer::getInstance()->display('@erm/risk_form.html.twig', [
           'item'          => $this,
           'params'        => $options,
           'probabilities' => ['0' => '-'] + Scale::probabilities(),
           'impacts'       => ['0' => '-'] + Scale::impacts(),
           'treatments'    => Scale::treatments(),
           'statuses'      => $statuses,
           'can_accept'    => Rights::canAccept(),
           'acceptance_only' => !$this->isNewItem() && $this->canUpdateItem() && !$this->canEditAssessment(),
           'acceptance'    => $acceptance,
           'effectiveness' => Scale::effectiveness(),
           'velocities'    => Scale::velocities(),
           'trends'        => Scale::trends(),
           'inherent_badge' => Scale::badge($this->fields['inherent_level'] ?? '', (int) ($this->fields['inherent_score'] ?? 0) ?: null),
           'residual_badge' => Scale::badge($this->fields['residual_level'] ?? '', (int) ($this->fields['residual_score'] ?? 0) ?: null),
           'appetite_badge' => $this->isNewItem() ? '' : Scale::appetiteBadge($pos),
           'target_badge'   => Scale::badge($this->fields['target_level'] ?? '', (int) ($this->fields['target_score'] ?? 0) ?: null),
           'quality'        => $this->isNewItem() ? [] : array_values(array_intersect_key(MethodChecks::messages(), array_flip(MethodChecks::check(
               $this->fields,
               countElementsInTable(Action::getTable(), ['plugin_erm_risks_id' => $this->getID()]),
               $pos[0],
               date('Y-m-d')
           )))),
           'impact_help'    => self::impactHelp((int) ($this->fields['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0))),
           'acceptance_state' => AcceptanceState::state((string) ($this->fields['status'] ?? ''), $this->fields['acceptance_valid_until'] ?? null, date('Y-m-d')),
           'controls'       => $this->isNewItem() ? null : (ControlFeed::summaryByRisk([$this->getID()])[$this->getID()] ?? null),
           'grc_controls'   => ControlFeed::available(),
        ]);
        return true;
    }

    /**
     * Impact scale explained with the economic criteria of the entity (or generic guidance when not defined).
     */
    public static function impactHelp(int $entityId): string
    {
        $thresholds = EntityConfig::impactThresholds($entityId);
        $parts = [];
        foreach ($thresholds as $level => $min) {
            if ($min !== null) {
                $parts[] = sprintf(__('%1$s from %2$s', 'erm'), Scale::impacts()[$level], Scale::money($min));
            }
        }
        $money = $parts ? sprintf(__('Economic criteria of your company: %s.', 'erm'), implode(' · ', $parts)) . ' ' : '';
        return $money . __('Take the worst consequence among money, customers, regulators, reputation and people: a regulatory sanction or a public incident can be "Major" even with a small loss.', 'erm');
    }

    /** Active risks of the visible entities, with their category. */
    public static function activeRisks(): array
    {
        global $DB;
        $rows = [];
        $iter = $DB->request([
           'SELECT'    => ['r.*', 'c.completename AS category', 'c.plugin_erm_categories_id AS parent_category'],
           'FROM'      => self::getTable() . ' AS r',
           'LEFT JOIN' => [Category::getTable() . ' AS c' => ['ON' => ['r' => 'plugin_erm_categories_id', 'c' => 'id']]],
           'WHERE'     => ['r.is_deleted' => 0, 'NOT' => ['r.status' => 'closed']] + getEntitiesRestrictCriteria('r', '', '', true)
               + (Rights::onlyOwn() ? [Rights::ownCriteria('r')] : []),
           'ORDER'     => ['r.residual_score DESC', 'r.inherent_score DESC'],
        ]);
        foreach ($iter as $row) {
            [$app, $tol] = Category::effectiveAppetite((int) $row['plugin_erm_categories_id'], (int) $row['entities_id']);
            $row['appetite'] = Scale::appetitePosition($row['residual_level'] ?: $row['inherent_level'], $app, $tol);
            $rows[] = $row;
        }
        return $rows;
    }
}

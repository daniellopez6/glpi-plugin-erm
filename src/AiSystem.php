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

/**
 * AI system of the organization (ISO/IEC 42001 inventory, EU AI Act classification): purpose, provider and model,
 * data, affected people, human oversight and lifecycle status. Impact assessments and corporate risks hang from it.
 */
class AiSystem extends CommonDBTM
{
    public static string $rightname = Rights::AI;
    public bool $dohistory = true;
    protected bool $usenotepad = true;

    public static function getTypeName($nb = 0)
    {
        return _n('AI system', 'AI systems', $nb, 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-brain';
    }

    public static function getSearchURL($full = true)
    {
        return plugin_erm_url('front/aisystem.php', $full);
    }

    public static function getFormURL($full = true)
    {
        return plugin_erm_url('front/aisystem.form.php', $full);
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        $links = ['search' => self::getSearchURL(false)];
        if (self::canCreate()) {
            $links['add'] = self::getFormURL(false);
        }
        return ['title' => self::getTypeName(2), 'page' => self::getSearchURL(false), 'icon' => self::getIcon(), 'links' => $links];
    }

    public static function aiTypes(): array
    {
        return [
            'generative'  => __('Generative AI / LLM (text, chatbot, agent)', 'erm'),
            'speech'      => __('Speech (recognition, synthesis, voicebot)', 'erm'),
            'analytics'   => __('Speech and text analytics (sentiment, quality)', 'erm'),
            'prediction'  => __('Prediction or scoring (machine learning)', 'erm'),
            'vision'      => __('Computer vision / biometrics', 'erm'),
            'automation'  => __('Automated decision or routing', 'erm'),
            'other'       => __('Other', 'erm'),
        ];
    }

    public static function deployments(): array
    {
        return [
            'saas_api'  => __('Third-party service (SaaS / API)', 'erm'),
            'in_house'  => __('Developed in-house', 'erm'),
            'fine_tuned' => __('Third-party model adapted in-house', 'erm'),
            'embedded'  => __('Embedded in a purchased product', 'erm'),
        ];
    }

    public static function aiActRoles(): array
    {
        return ['deployer' => __('Deployer (uses the system)', 'erm'), 'provider' => __('Provider (develops or places on the market)', 'erm'),
                'both' => __('Provider and deployer', 'erm')];
    }

    public static function aiActClasses(): array
    {
        return [
            'unassessed' => __('Not classified', 'erm'),
            'minimal'    => __('Minimal risk', 'erm'),
            'limited'    => __('Limited risk (transparency obligations)', 'erm'),
            'high'       => __('High risk (Annex III)', 'erm'),
            'prohibited' => __('Prohibited practice', 'erm'),
        ];
    }

    public static function oversightModes(): array
    {
        return ['in_loop' => __('Human in the loop (approves each output)', 'erm'), 'on_loop' => __('Human on the loop (monitors, can intervene)', 'erm'),
                'none' => __('No human oversight (fully automated)', 'erm')];
    }

    public static function statuses(): array
    {
        return ['planned' => __('Planned', 'erm'), 'development' => __('In development / pilot', 'erm'),
                'production' => __('In production', 'erm'), 'retired' => __('Retired', 'erm')];
    }

    public static function affectedGroups(): array
    {
        return ['customers' => __('Customers / end users', 'erm'), 'employees' => __('Employees / agents', 'erm'),
                'candidates' => __('Job candidates', 'erm'), 'public' => __('General public', 'erm'), 'minors' => __('Minors or vulnerable people', 'erm')];
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(AiAssessment::class, $tabs, $options);
        $this->addStandardTab(Risk_Item::class, $tabs, $options);
        $this->addStandardTab(Document_Item::class, $tabs, $options);
        $this->addStandardTab('Notepad', $tabs, $options);
        $this->addStandardTab(Log::class, $tabs, $options);
        return $tabs;
    }

    public function prepareInputForAdd($input)
    {
        return $this->clean($input);
    }

    public function prepareInputForUpdate($input)
    {
        if (!Rights::entityChangeAllowed($input, $this->fields)) {
            return false;
        }
        return $this->clean($input);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>|false
     */
    private function clean(array $input): array|false
    {
        if (array_key_exists('name', $input) && trim((string) $input['name']) === '') {
            \Session::addMessageAfterRedirect(__('The AI system must have a name.', 'erm'), false, ERROR);
            return false;
        }
        $enums = ['ai_type' => self::aiTypes(), 'deployment' => self::deployments(), 'aiact_role' => self::aiActRoles(),
                  'aiact_class' => self::aiActClasses(), 'human_oversight' => self::oversightModes(), 'status' => self::statuses()];
        foreach ($enums as $field => $allowed) {
            if (array_key_exists($field, $input) && !array_key_exists((string) $input[$field], $allowed)) {
                $input[$field] = (string) array_key_first($allowed);
            }
        }
        if (array_key_exists('affected', $input)) {
            $groups = array_values(array_intersect((array) $input['affected'], array_keys(self::affectedGroups())));
            $input['affected_groups'] = implode(',', $groups);
            unset($input['affected']);
        }
        foreach (['personal_data', 'sensitive_data'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $input[$flag] = (int) (bool) $input[$flag];
            }
        }
        return $input;
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb([AiAssessment::class]);
        global $DB;
        $DB->delete(Risk_Item::getTable(), ['itemtype' => self::class, 'items_id' => $this->getID()]);
    }

    /**
     * Governance status of an AI system: last approved assessment and gap (AiImpact::gap).
     *
     * @return array{gap:string, last:?array<string,mixed>}
     */
    public static function governance(array $system): array
    {
        global $DB;
        $last = $DB->request(['FROM' => AiAssessment::getTable(), 'WHERE' => [
            'plugin_erm_aisystems_id' => $system['id'], 'status' => 'approved',
        ], 'ORDER' => ['assessment_date DESC', 'id DESC'], 'LIMIT' => 1])->current();
        return [
            'gap'  => AiImpact::gap((string) $system['status'], (string) $system['aiact_class'], $last['next_assessment_date'] ?? null, date('Y-m-d'), $last !== null),
            'last' => $last ?: null,
        ];
    }

    public static function gapLabels(): array
    {
        return [
            'missing'    => __('Requires an approved impact assessment and has none', 'erm'),
            'expired'    => __('The impact assessment must be renewed', 'erm'),
            'prohibited' => __('Classified as a prohibited practice: it must not be used', 'erm'),
        ];
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $gov = $this->isNewItem() ? ['gap' => 'none', 'last' => null] : self::governance($this->fields);
        TemplateRenderer::getInstance()->display('@erm/aisystem_form.html.twig', [
            'item'        => $this,
            'params'      => $options,
            'types'       => self::aiTypes(),
            'deployments' => self::deployments(),
            'roles'       => self::aiActRoles(),
            'classes'     => self::aiActClasses(),
            'oversight'   => self::oversightModes(),
            'statuses'    => self::statuses(),
            'affected'    => self::affectedGroups(),
            'selected_affected' => array_filter(explode(',', (string) ($this->fields['affected_groups'] ?? ''))),
            'gap'         => $gov['gap'],
            'gap_label'   => self::gapLabels()[$gov['gap']] ?? '',
            'last_level'  => $gov['last'] ? Scale::badge($gov['last']['overall_level']) : '',
            'last_date'   => $gov['last']['assessment_date'] ?? '',
        ]);
        return true;
    }

    public function rawSearchOptions()
    {
        $t = self::getTable();
        $tab = [['id' => 'common', 'name' => self::getTypeName(2)]];
        $tab[] = ['id' => 1, 'table' => $t, 'field' => 'name', 'name' => __('Name'), 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 2, 'table' => $t, 'field' => 'id', 'name' => __('ID'), 'datatype' => 'number', 'massiveaction' => false];
        $tab[] = ['id' => 3, 'table' => $t, 'field' => 'ai_type', 'name' => __('AI type', 'erm'), 'datatype' => 'specific', 'searchtype' => ['equals', 'notequals']];
        $tab[] = ['id' => 4, 'table' => $t, 'field' => 'status', 'name' => __('Status', 'erm'), 'datatype' => 'specific', 'searchtype' => ['equals', 'notequals']];
        $tab[] = ['id' => 5, 'table' => $t, 'field' => 'aiact_class', 'name' => __('AI Act class', 'erm'), 'datatype' => 'specific', 'searchtype' => ['equals', 'notequals']];
        $tab[] = ['id' => 6, 'table' => 'glpi_suppliers', 'field' => 'name', 'name' => __('Provider', 'erm'), 'datatype' => 'dropdown'];
        $tab[] = ['id' => 7, 'table' => 'glpi_users', 'field' => 'name', 'name' => __('Owner', 'erm'), 'datatype' => 'dropdown', 'right' => 'all'];
        $tab[] = ['id' => 8, 'table' => $t, 'field' => 'model_name', 'name' => __('Model', 'erm'), 'datatype' => 'string'];
        $tab[] = ['id' => 9, 'table' => $t, 'field' => 'deployment', 'name' => __('Deployment', 'erm'), 'datatype' => 'specific', 'searchtype' => ['equals', 'notequals']];
        $tab[] = ['id' => 10, 'table' => $t, 'field' => 'personal_data', 'name' => __('Personal data', 'erm'), 'datatype' => 'bool'];
        $tab[] = ['id' => 11, 'table' => $t, 'field' => 'human_oversight', 'name' => __('Human oversight', 'erm'), 'datatype' => 'specific', 'searchtype' => ['equals', 'notequals']];
        $tab[] = ['id' => 12, 'table' => 'glpi_groups', 'field' => 'completename', 'name' => __('Responsible group', 'erm'), 'datatype' => 'dropdown'];
        $tab[] = ['id' => 13, 'table' => $t, 'field' => 'purpose', 'name' => __('Purpose', 'erm'), 'datatype' => 'text'];
        $tab[] = ['id' => 14, 'table' => $t, 'field' => 'date_mod', 'name' => __('Last update'), 'datatype' => 'datetime', 'massiveaction' => false];
        $tab[] = ['id' => 80, 'table' => 'glpi_entities', 'field' => 'completename', 'name' => \Entity::getTypeName(1), 'datatype' => 'dropdown'];
        return $tab;
    }

    private static function enumFor(string $field): array
    {
        return match ($field) {
            'ai_type'         => self::aiTypes(),
            'status'          => self::statuses(),
            'aiact_class'     => self::aiActClasses(),
            'deployment'      => self::deployments(),
            'human_oversight' => self::oversightModes(),
            default           => [],
        };
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $enum = self::enumFor((string) $field);
        return $enum ? htmlescape($enum[$values[$field]] ?? $values[$field]) : parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $enum = self::enumFor((string) $field);
        return $enum ? Dropdown::showFromArray($name, $enum, ['display' => false, 'value' => $values[$field] ?? ''])
            : parent::getSpecificValueToSelect($field, $name, $values, $options);
    }
}

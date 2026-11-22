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
use Session;

/**
 * Guided risk registration for users who do not know the methodology: plain-language questions (optionally starting
 * from a template of the risk library) that create a complete risk and its first treatment action.
 */
class RiskWizard extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Risk wizard', 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-wand';
    }

    public static function getWizardURL(string $template = ''): string
    {
        return plugin_erm_url('front/riskwizard.php') . ($template !== '' ? '?template=' . rawurlencode($template) : '');
    }

    public static function getLibraryURL(): string
    {
        return plugin_erm_url('front/risklibrary.php');
    }

    public static function library(): array
    {
        return RiskLibrary::load(RiskLibrary::languageFile((string) ($_SESSION['glpilanguage'] ?? 'en_GB')));
    }

    /** Plain-language probability answers (1-5). */
    public static function probabilityAnswers(): array
    {
        return [
            1 => __('Almost never: less than once every 20 years', 'erm'),
            2 => __('Unlikely: once every 4 to 20 years', 'erm'),
            3 => __('Possible: once every 2 to 4 years', 'erm'),
            4 => __('Likely: it will probably happen this year', 'erm'),
            5 => __('Almost certain: this year, or several times a year', 'erm'),
        ];
    }

    /** Plain-language impact answers (1-5), with the economic criteria of the entity. */
    public static function impactAnswers(int $entityId): array
    {
        $text = [
            1 => __('Insignificant: a minor nuisance, solved the same day', 'erm'),
            2 => __('Minor: small loss, some customers affected, no public exposure', 'erm'),
            3 => __('Moderate: relevant loss, complaints, a breached service level', 'erm'),
            4 => __('Major: large loss, regulatory sanction, loss of an important client, media exposure', 'erm'),
            5 => __('Catastrophic: threatens the continuity of the business or its licence', 'erm'),
        ];
        foreach (EntityConfig::impactThresholds($entityId) as $level => $min) {
            if ($min !== null) {
                $text[$level] .= ' (' . sprintf(__('from %s', 'erm'), Scale::money($min)) . ')';
            }
        }
        return $text;
    }

    public static function show(string $templateKey = ''): void
    {
        $library = self::library();
        $template = $library[$templateKey] ?? null;
        $entityId = (int) ($_SESSION['glpiactive_entity'] ?? 0);
        $categoryId = $template ? (Category::idByKey($template['category']) ?: Category::idByKey(RiskLibrary::rootOf($template['category']))) : 0;
        TemplateRenderer::getInstance()->display('@erm/risk_wizard.html.twig', [
            'template'      => $template,
            'probabilities' => self::probabilityAnswers(),
            'impacts'       => self::impactAnswers($entityId),
            'category_dropdown' => Category::dropdown(['name' => 'plugin_erm_categories_id', 'value' => $categoryId, 'entity' => $entityId, 'display' => false]),
            'owner_dropdown'    => \User::dropdown(['name' => 'users_id', 'right' => 'all', 'entity' => $entityId, 'value' => Session::getLoginUserID(), 'display' => false]),
            'action_owner_dropdown' => \User::dropdown(['name' => 'action_users_id', 'right' => 'all', 'entity' => $entityId, 'display' => false]),
            'can_add_action' => Session::haveRight(Rights::TREATMENT, CREATE),
            'action'        => self::getWizardURL(),
            'library_url'   => self::getLibraryURL(),
            'review_default' => date('Y-m-d', strtotime('+12 months')),
            'entity'        => \Dropdown::getDropdownName('glpi_entities', $entityId),
        ]);
    }

    /**
     * Creates the risk (and its first action) from the wizard answers. Returns the risk id or 0.
     *
     * @param array<string,mixed> $post
     */
    public static function create(array $post): int
    {
        $ip = Validator::range($post['inherent_probability'] ?? 0, 0, 5);
        $ii = Validator::range($post['inherent_impact'] ?? 0, 0, 5);
        $controls = (string) ($post['controls_work'] ?? '');
        $treatment = array_key_exists((string) ($post['treatment'] ?? ''), Scale::treatments()) ? (string) $post['treatment'] : '';
        $input = [
            'name'                     => trim((string) ($post['name'] ?? '')),
            'entities_id'              => (int) ($_SESSION['glpiactive_entity'] ?? 0),
            'plugin_erm_categories_id' => (int) ($post['plugin_erm_categories_id'] ?? 0),
            'users_id'                 => (int) ($post['users_id'] ?? 0),
            'description'              => trim((string) ($post['description'] ?? '')),
            'causes'                   => trim((string) ($post['causes'] ?? '')),
            'consequences'             => trim((string) ($post['consequences'] ?? '')),
            'inherent_probability'     => $ip,
            'inherent_impact'          => $ii,
            'existing_controls'        => $controls === 'none' ? '' : trim((string) ($post['existing_controls'] ?? '')),
            'control_effectiveness'    => WizardMapper::effectiveness($controls),
            'residual_probability'     => WizardMapper::residual((string) ($post['residual_probability'] ?? 'same'), $ip, $controls),
            'residual_impact'          => WizardMapper::residual((string) ($post['residual_impact'] ?? 'same'), $ii, $controls),
            'treatment'                => $treatment,
            'justification'            => trim((string) ($post['justification'] ?? '')),
            'status'                   => WizardMapper::status($ip, $ii, $treatment),
            'review_date'              => (string) ($post['review_date'] ?? ''),
        ];
        if ($input['name'] === '') {
            Session::addMessageAfterRedirect(__('Describe what could happen (the risk).', 'erm'), false, ERROR);
            return 0;
        }
        $risk = new Risk();
        if (!$risk->can(-1, CREATE, $input)) {
            return 0;
        }
        $id = (int) $risk->add($input);
        if (!$id) {
            return 0;
        }
        $actionName = trim((string) ($post['action_name'] ?? ''));
        if ($actionName !== '' && Session::haveRight(Rights::TREATMENT, CREATE)) {
            (new Action())->add([
                'plugin_erm_risks_id' => $id,
                'name'                => $actionName,
                'users_id'            => (int) ($post['action_users_id'] ?? 0),
                'due_date'            => (string) ($post['action_due_date'] ?? ''),
            ]);
        }
        return $id;
    }

    /** Page of the risk library, grouped by root category. */
    public static function showLibrary(): void
    {
        $groups = [];
        foreach (self::library() as $t) {
            $root = RiskLibrary::rootOf($t['category']);
            $id = Category::idByKey($root);
            $groups[$root]['name'] ??= $id ? \Dropdown::getDropdownName(Category::getTable(), $id) : $root;
            $groups[$root]['templates'][] = $t;
        }
        TemplateRenderer::getInstance()->display('@erm/risk_library.html.twig', [
            'groups'      => $groups,
            'wizard_url'  => self::getWizardURL(),
            'treatments'  => Scale::treatments(),
        ]);
    }
}

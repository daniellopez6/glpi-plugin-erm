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
 * Impact assessment of an AI system (ISO/IEC 42001 6.1.4 / 8.4, ISO/IEC 42005 criteria): score and rationale for
 * each dimension, overall impact, mitigations and next assessment. Draft -> submitted -> approved or rejected; the
 * approver must not be the assessor and approved assessments cannot be modified.
 */
class AiAssessment extends CommonDBChild
{
    public static string $itemtype = AiSystem::class;
    public static string $items_id = 'plugin_erm_aisystems_id';
    public static string $rightname = Rights::AI;
    public static int $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;
    public bool $dohistory = true;

    public static function getTypeName($nb = 0)
    {
        return _n('AI impact assessment', 'AI impact assessments', $nb, 'erm');
    }

    public static function getIcon()
    {
        return 'ti ti-clipboard-check';
    }

    public static function getFormURL($full = true)
    {
        return plugin_erm_url('front/aiassessment.form.php', $full);
    }

    public static function statuses(): array
    {
        return ['draft' => __('Draft', 'erm'), 'submitted' => __('Submitted for approval', 'erm'),
                'approved' => __('Approved', 'erm'), 'rejected' => __('Rejected', 'erm')];
    }

    public static function canApproveRight(): bool
    {
        return Session::haveRight(Rights::AI, Rights::APPROVE);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof AiSystem && !$item->isNewItem()) {
            $n = countElementsInTable(self::getTable(), ['plugin_erm_aisystems_id' => $item->getID()]);
            return self::createTabEntry(self::getTypeName(2), $n, null, self::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $DB;
        if (!$item instanceof AiSystem) {
            return false;
        }
        $rows = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_erm_aisystems_id' => $item->getID()], 'ORDER' => ['assessment_date DESC', 'id DESC']]) as $r) {
            $r['assessor'] = $r['users_id'] ? getUserName((int) $r['users_id']) : '';
            $r['approver'] = $r['users_id_approval'] ? getUserName((int) $r['users_id_approval']) : '';
            $r['level_badge'] = Scale::badge($r['overall_level']);
            $r['residual_badge'] = $r['residual_level'] ? Scale::badge($r['residual_level']) : '';
            $r['status_label'] = self::statuses()[$r['status']] ?? $r['status'];
            $rows[] = $r;
        }
        TemplateRenderer::getInstance()->display('@erm/aiassessments_tab.html.twig', [
            'rows'    => $rows,
            'new_url' => self::getFormURL() . '?plugin_erm_aisystems_id=' . $item->getID(),
            'url'     => self::getFormURL(),
            'can_add' => Session::haveRight(Rights::AI, CREATE),
        ]);
        return true;
    }

    // ------------------------------------------------------------------ workflow
    public function prepareInputForAdd($input)
    {
        $system = new AiSystem();
        if (!$system->getFromDB((int) ($input['plugin_erm_aisystems_id'] ?? 0))) {
            return false;
        }
        $input['entities_id'] = $system->fields['entities_id'];
        $input['is_recursive'] = $system->fields['is_recursive'];
        $input['users_id'] = (int) Session::getLoginUserID();
        $input['editors'] = (string) Session::getLoginUserID();
        $input['status'] = 'draft';
        unset($input['users_id_approval'], $input['date_approval']);
        return $this->clean($input);
    }

    public function prepareInputForUpdate($input)
    {
        if (($this->fields['status'] ?? '') === 'approved') {
            Session::addMessageAfterRedirect(__('An approved assessment cannot be modified: create a new one.', 'erm'), false, ERROR);
            return false;
        }
        // A submitted assessment is frozen until it is approved or rejected: the approver decides on what was submitted
        if (($this->fields['status'] ?? '') === 'submitted') {
            Session::addMessageAfterRedirect(__('A submitted assessment cannot be modified: it must be approved or rejected first.', 'erm'), false, ERROR);
            return false;
        }
        // The workflow fields only change through the dedicated actions
        unset(
            $input['status'],
            $input['users_id'],
            $input['users_id_approval'],
            $input['date_approval'],
            $input['editors'],
            $input['plugin_erm_aisystems_id'],
            $input['entities_id'],
            $input['is_recursive']
        );
        // Everyone who edits the assessment is recorded: none of them may approve it
        $input['editors'] = implode(',', array_unique(array_merge(self::editorsOf($this->fields), [(int) Session::getLoginUserID()])));
        if (!empty($input['_submit'])) {
            $input['status'] = 'submitted';
        }
        return $this->clean($input);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>|false
     */
    private function clean(array $input): array|false
    {
        $input = Validator::apply($input, ['assessment_date' => 'date', 'next_assessment_date' => 'date']);
        if ($input === false) {
            return false;
        }
        if (($input['assessment_date'] ?? null) === 'NULL') {
            $input['assessment_date'] = date('Y-m-d');
        }
        $scores = [];
        foreach (AiImpact::DIMENSIONS as $d) {
            if (array_key_exists("score_$d", $input)) {
                $input["score_$d"] = Validator::range($input["score_$d"], 0, 5);
            }
            $scores[$d] = (int) ($input["score_$d"] ?? ($this->fields["score_$d"] ?? 0));
        }
        $input['overall_level'] = AiImpact::overall($scores);
        if (isset($input['residual_level']) && !in_array($input['residual_level'], Scale::LEVELS, true)) {
            $input['residual_level'] = '';
        }
        if (($input['status'] ?? '') === 'submitted' && $input['overall_level'] === '') {
            Session::addMessageAfterRedirect(__('Score at least one dimension before submitting the assessment.', 'erm'), false, ERROR);
            return false;
        }
        return $input;
    }

    /**
     * Users who created or edited the assessment.
     *
     * @return list<int>
     */
    public static function editorsOf(array $fields): array
    {
        $ids = array_map('intval', array_filter(explode(',', (string) ($fields['editors'] ?? ''))));
        $ids[] = (int) ($fields['users_id'] ?? 0);
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Approves or rejects a submitted assessment (approval right, never its author or an editor).
     */
    public function decide(bool $approve, string $comment): bool
    {
        if (($this->fields['status'] ?? '') !== 'submitted') {
            Session::addMessageAfterRedirect(__('Only a submitted assessment can be approved or rejected.', 'erm'), false, ERROR);
            return false;
        }
        $approver = (int) Session::getLoginUserID();
        if (
            !self::canApproveRight() || !AiImpact::canApprove((int) $this->fields['users_id'], $approver)
            || in_array($approver, self::editorsOf($this->fields), true)
        ) {
            Session::addMessageAfterRedirect(__('The assessment must be approved by someone with the approval right who did not write or edit it.', 'erm'), false, ERROR);
            return false;
        }
        global $DB;
        // Direct update: approved assessments are otherwise read-only
        $ok = $DB->update(self::getTable(), [
            'status'            => $approve ? 'approved' : 'rejected',
            'users_id_approval' => (int) Session::getLoginUserID(),
            'date_approval'     => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            'approval_comment'  => $comment,
        ], ['id' => $this->getID()]);
        \Log::history($this->getID(), self::class, [0, '', $approve ? __('Assessment approved', 'erm') : __('Assessment rejected', 'erm')], '', \Log::HISTORY_LOG_SIMPLE_MESSAGE);
        return (bool) $ok;
    }

    /**
     * Creates the corporate risk of a significant AI impact (category "Artificial intelligence") linked to the system.
     */
    public function createRisk(): int
    {
        $system = new AiSystem();
        if (!$system->getFromDB((int) $this->fields['plugin_erm_aisystems_id'])) {
            return 0;
        }
        $scores = [];
        foreach (AiImpact::DIMENSIONS as $d) {
            $scores[$d] = (int) $this->fields["score_$d"];
        }
        $worst = AiImpact::worstDimension($scores);
        $dims = AiImpact::dimensions();
        $risk = new Risk();
        $data = [
            'name'                     => sprintf(__('AI: %1$s - %2$s', 'erm'), $system->fields['name'], $dims[$worst] ?? ''),
            'entities_id'              => $system->fields['entities_id'],
            'plugin_erm_categories_id' => Category::aiRootId(),
            'users_id'                 => $system->fields['users_id'],
            'groups_id'                => $system->fields['groups_id'],
            'description'              => (string) $system->fields['purpose'],
            'consequences'             => (string) ($this->fields["notes_$worst"] ?? ''),
            'existing_controls'        => (string) $this->fields['mitigations'],
            'status'                   => 'identified',
        ];
        // The user must be allowed to create risks in the entity of the AI system
        if (!$risk->can(-1, CREATE, $data)) {
            return 0;
        }
        $id = $risk->add($data);
        if ($id) {
            (new Risk_Item())->add(['plugin_erm_risks_id' => $id, 'itemtype' => AiSystem::class, 'items_id' => $system->getID(),
                                    'comment' => sprintf(__('Impact assessment of %s', 'erm'), $this->fields['assessment_date'])]);
        }
        return (int) $id;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        if ($this->isNewItem()) {
            $this->fields['plugin_erm_aisystems_id'] = (int) ($options['plugin_erm_aisystems_id'] ?? 0);
            $this->fields['assessment_date'] = date('Y-m-d');
            $this->fields['next_assessment_date'] = date('Y-m-d', strtotime('+12 months'));
        }
        $system = new AiSystem();
        $system->getFromDB((int) $this->fields['plugin_erm_aisystems_id']);
        $status = (string) ($this->fields['status'] ?? 'draft');
        $isAuthor = in_array((int) Session::getLoginUserID(), self::editorsOf($this->fields), true);
        TemplateRenderer::getInstance()->display('@erm/aiassessment_form.html.twig', [
            'item'         => $this,
            'params'       => $options + ['canedit' => !in_array($status, ['approved', 'submitted'], true)
                && Session::haveRight(Rights::AI, $this->isNewItem() ? CREATE : UPDATE)],
            'system'       => $system->isNewItem() ? null : $system,
            'dimensions'   => AiImpact::dimensions(),
            'scores'       => AiImpact::scores(),
            'levels'       => ['' => '-'] + Scale::levels(),
            'status_label' => AiAssessment::statuses()[$status] ?? $status,
            'overall'      => Scale::badge($this->fields['overall_level'] ?? ''),
            'assessor'     => !empty($this->fields['users_id']) ? getUserName((int) $this->fields['users_id']) : '',
            'approval'     => !empty($this->fields['users_id_approval'])
                ? sprintf('%s - %s', getUserName((int) $this->fields['users_id_approval']), \Html::convDateTime($this->fields['date_approval'])) : '',
            'can_submit'   => !$this->isNewItem() && in_array($status, ['draft', 'rejected'], true) && Session::haveRight(Rights::AI, UPDATE),
            'can_decide'   => !$this->isNewItem() && $status === 'submitted' && self::canApproveRight() && !$isAuthor,
            'author_blocked' => $status === 'submitted' && self::canApproveRight() && $isAuthor,
            'can_risk'     => !$this->isNewItem() && $status === 'approved' && Risk::canCreate()
                && in_array($this->fields['overall_level'], ['medium', 'high', 'critical'], true),
            'action'       => self::getFormURL(),
        ]);
        return true;
    }
}

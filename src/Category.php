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

use CommonTreeDropdown;
use Dropdown;
use Session;

/**
 * Categoría de riesgo corporativo (taxonomía jerárquica configurable) con apetito y tolerancia.
 */
class Category extends CommonTreeDropdown
{
    public static string $rightname = Rights::CONFIG;

    /** La taxonomía y el apetito se pueden consultar con permiso de configuración o de lectura del registro. */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ) || Rights::canReadSome();
    }
    public bool $dohistory = true;
    public bool $can_be_translated = false;

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Categorías de riesgo corporativo' : 'Categoría de riesgo corporativo';
    }

    public static function getIcon()
    {
        return 'ti ti-sitemap';
    }

    public static function getMenuName()
    {
        return 'Categorías y apetito (ERM)';
    }

    public function getAdditionalFields()
    {
        return array_merge(parent::getAdditionalFields(), [
            ['name' => 'appetite_level', 'label' => 'Apetito de riesgo (nivel residual máximo deseado)', 'type' => 'erm_level'],
            ['name' => 'tolerance_level', 'label' => 'Tolerancia (nivel residual máximo admisible)', 'type' => 'erm_level'],
            ['name' => 'appetite_statement', 'label' => 'Declaración de apetito', 'type' => 'textarea'],
        ]);
    }

    public function displaySpecificTypeField($ID, $field = [], array $options = [])
    {
        if (($field['type'] ?? '') === 'erm_level') {
            Dropdown::showFromArray($field['name'], ['' => 'Hereda de la categoría superior'] + Scale::levels(), [
                'value' => $this->fields[$field['name']] ?? '',
            ]);
        }
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();
        $tab[] = ['id' => 101, 'table' => $this->getTable(), 'field' => 'appetite_level', 'name' => 'Apetito', 'datatype' => 'specific'];
        $tab[] = ['id' => 102, 'table' => $this->getTable(), 'field' => 'tolerance_level', 'name' => 'Tolerancia', 'datatype' => 'specific'];
        $tab[] = ['id' => 103, 'table' => $this->getTable(), 'field' => 'appetite_statement', 'name' => 'Declaración de apetito', 'datatype' => 'text'];
        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if (in_array($field, ['appetite_level', 'tolerance_level'], true)) {
            return $values[$field] ? Scale::badge($values[$field]) : '-';
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if (in_array($field, ['appetite_level', 'tolerance_level'], true)) {
            return Dropdown::showFromArray($name, ['' => '-'] + Scale::levels(), ['display' => false, 'value' => $values[$field] ?? '']);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Apetito y tolerancia efectivos: si la categoría no los define, se heredan de la superior.
     * @return array{0:string,1:string}
     */
    public static function effectiveAppetite(int $id): array
    {
        $appetite = $tolerance = '';
        $cat = new self();
        $guard = 0;
        while ($id > 0 && $guard++ < 10 && $cat->getFromDB($id)) {
            $appetite  = $appetite ?: (string) $cat->fields['appetite_level'];
            $tolerance = $tolerance ?: (string) $cat->fields['tolerance_level'];
            if ($appetite && $tolerance) {
                break;
            }
            $id = (int) $cat->fields['plugin_erm_categories_id'];
        }
        return [$appetite, $tolerance];
    }

    /** Taxonomía inicial basada en ISO 31000 / COSO ERM adaptada a una entidad financiera. */
    public static function createDefaultTaxonomy(): void
    {
        $tree = [
            ['Estratégico', 'medium', 'high', 'Se aceptan riesgos moderados para crecer en el canal digital, sin comprometer la solvencia.',
                ['Modelo de negocio y competencia', 'Proyectos e iniciativas estratégicas', 'Gobierno corporativo']],
            ['Financiero', 'medium', 'high', 'Se aceptan riesgos financieros moderados acordes al negocio de crédito al consumo; ningún riesgo puede comprometer la liquidez mínima ni el capital regulatorio.',
                ['Crédito (incobrabilidad)', 'Liquidez y fondeo', 'Mercado (tasa y tipo de cambio)', 'Capital y solvencia']],
            ['Operacional', 'medium', 'high', 'Se toleran fallas operativas menores con recuperación dentro del RTO.',
                ['Procesos', 'Personas', 'Tecnología y seguridad de la información', 'Terceros y proveedores', 'Continuidad del negocio']],
            ['Cumplimiento y legal', 'low', 'medium', 'Tolerancia mínima: los incumplimientos regulatorios o legales se corrigen con prioridad; nunca se aceptan de forma deliberada.',
                ['Regulatorio (BCRA / UIF / AAIP)', 'Prevención de lavado de activos (PLA/FT)', 'Protección del consumidor', 'Litigios y contratos']],
            ['Fraude', 'low', 'medium', 'Cero tolerancia al fraude interno; el fraude externo se mantiene acotado con controles de originación.',
                ['Fraude interno', 'Fraude externo (identidad, originación)']],
            ['Reputacional', 'medium', 'high', 'Se protege la confianza de clientes, reguladores y fondeadores; los eventos con exposición pública se escalan al Directorio.', []],
            ['ESG y climático', 'medium', 'high', 'Riesgos ambientales, sociales y climáticos gestionados de forma progresiva.',
                ['Climático (físico y de transición)', 'Social y derechos del consumidor']],
        ];
        foreach ($tree as [$name, $app, $tol, $stmt, $children]) {
            $pid = (new self())->add(['name' => $name, 'plugin_erm_categories_id' => 0, 'entities_id' => 0, 'is_recursive' => 1,
                                      'appetite_level' => $app, 'tolerance_level' => $tol, 'appetite_statement' => $stmt]);
            foreach ($children as $child) {
                (new self())->add(['name' => $child, 'plugin_erm_categories_id' => $pid, 'entities_id' => 0, 'is_recursive' => 1]);
            }
        }
    }
}

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

use Glpi\DBAL\QuerySubQuery;
use Session;

/**
 * Modelo de permisos del plugin ERM.
 *
 * - plugin_erm            Registro de riesgos: READ (todos) o READ_OWNED (sólo los propios), UPDATE / UPDATE_OWNED,
 *                         CREATE, DELETE, PURGE, notas.
 * - plugin_erm_treatment  Tratamiento y seguimiento: acciones y mediciones de KRI (CREATE, UPDATE, PURGE).
 * - plugin_erm_config     Taxonomía de categorías, apetito y tolerancia.
 * - plugin_erm_accept     Aceptación formal de riesgos (UPDATE). Quien es dueño del riesgo no puede aceptarlo.
 *
 * Un riesgo es "propio" si el usuario es su dueño, pertenece a su área responsable o es responsable de
 * alguna de sus acciones o indicadores.
 */
final class Rights
{
    public const RISK      = 'plugin_erm';
    public const TREATMENT = 'plugin_erm_treatment';
    public const CONFIG    = 'plugin_erm_config';
    public const ACCEPT    = 'plugin_erm_accept';

    public static function all(): array
    {
        return [self::RISK, self::TREATMENT, self::CONFIG, self::ACCEPT];
    }

    public static function canReadAll(): bool
    {
        return Session::haveRight(self::RISK, READ);
    }

    public static function canReadSome(): bool
    {
        return Session::haveRightsOr(self::RISK, [READ, READ_OWNED]);
    }

    /** El usuario sólo puede ver los riesgos propios. */
    public static function onlyOwn(): bool
    {
        return !self::canReadAll() && Session::haveRight(self::RISK, READ_OWNED);
    }

    public static function canAccept(): bool
    {
        return Session::haveRight(self::ACCEPT, UPDATE);
    }

    /** Criterio SQL (DBmysqlIterator) que restringe a los riesgos propios. */
    public static function ownCriteria(string $table = 'glpi_plugin_erm_risks'): array
    {
        $uid = (int) Session::getLoginUserID();
        $groups = array_map('intval', $_SESSION['glpigroups'] ?? []);
        $or = [
            "$table.users_id" => $uid,
            "$table.id" => new QuerySubQuery(['SELECT' => 'plugin_erm_risks_id', 'FROM' => Action::getTable(), 'WHERE' => ['users_id' => $uid]]),
        ];
        $or[] = ["$table.id" => new QuerySubQuery(['SELECT' => 'plugin_erm_risks_id', 'FROM' => Indicator::getTable(), 'WHERE' => ['users_id' => $uid]])];
        if ($groups) {
            $or["$table.groups_id"] = $groups;
        }
        return ['OR' => $or];
    }

    public static function isOwn(array $fields): bool
    {
        $uid = (int) Session::getLoginUserID();
        if ((int) ($fields['users_id'] ?? 0) === $uid) {
            return true;
        }
        if (in_array((int) ($fields['groups_id'] ?? 0), array_map('intval', $_SESSION['glpigroups'] ?? []), true)) {
            return true;
        }
        $rid = (int) ($fields['id'] ?? 0);
        return $rid > 0 && (countElementsInTable(Action::getTable(), ['plugin_erm_risks_id' => $rid, 'users_id' => $uid]) > 0
            || countElementsInTable(Indicator::getTable(), ['plugin_erm_risks_id' => $rid, 'users_id' => $uid]) > 0);
    }

    /** Valores por defecto al instalar/actualizar, por nombre de perfil. */
    public static function defaults(string $profile): array
    {
        $own = READ_OWNED | UPDATE_OWNED;
        return match ($profile) {
            'Super-Admin' => [self::RISK => ALLSTANDARDRIGHT | READNOTE | UPDATENOTE, self::TREATMENT => ALLSTANDARDRIGHT,
                              self::CONFIG => ALLSTANDARDRIGHT, self::ACCEPT => UPDATE],
            'Admin'       => [self::RISK => ALLSTANDARDRIGHT | READNOTE | UPDATENOTE, self::TREATMENT => ALLSTANDARDRIGHT,
                              self::CONFIG => ALLSTANDARDRIGHT, self::ACCEPT => 0],
            'Supervisor'  => [self::RISK => READ | UPDATE | CREATE, self::TREATMENT => READ | UPDATE | CREATE, self::CONFIG => READ, self::ACCEPT => 0],
            'Technician'  => [self::RISK => $own, self::TREATMENT => READ | UPDATE | CREATE, self::CONFIG => 0, self::ACCEPT => 0],
            'Observer', 'Read-Only' => [self::RISK => READ, self::TREATMENT => 0, self::CONFIG => 0, self::ACCEPT => 0],
            default       => [self::RISK => 0, self::TREATMENT => 0, self::CONFIG => 0, self::ACCEPT => 0],
        };
    }
}

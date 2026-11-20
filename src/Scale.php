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

/**
 * Escalas de evaluación 5x5 (ISO 31000), niveles, colores y apetito de riesgo.
 */
final class Scale
{
    public const LEVELS = ['low', 'medium', 'high', 'critical'];

    public static function probabilities(): array
    {
        return [
            1 => '1 - Rara (< 5% anual)',
            2 => '2 - Improbable (5-25%)',
            3 => '3 - Posible (25-50%)',
            4 => '4 - Probable (50-80%)',
            5 => '5 - Casi cierta (> 80%)',
        ];
    }

    public static function impacts(): array
    {
        return [
            1 => '1 - Insignificante (< ARS 5 M)',
            2 => '2 - Menor (ARS 5-50 M)',
            3 => '3 - Moderado (ARS 50-200 M)',
            4 => '4 - Mayor (ARS 200-800 M)',
            5 => '5 - Catastrófico (> ARS 800 M)',
        ];
    }

    public static function levels(): array
    {
        return ['low' => 'Bajo', 'medium' => 'Medio', 'high' => 'Alto', 'critical' => 'Crítico'];
    }

    public static function levelFor(int $probability, int $impact): string
    {
        if ($probability < 1 || $impact < 1) {
            return '';
        }
        $score = $probability * $impact;
        return match (true) {
            $score <= 4  => 'low',
            $score <= 9  => 'medium',
            $score <= 16 => 'high',
            default      => 'critical',
        };
    }

    public static function rank(?string $level): int
    {
        $i = array_search((string) $level, self::LEVELS, true);
        return $i === false ? -1 : $i;
    }

    public static function color(?string $level): string
    {
        return match ($level) {
            'low'      => '#2fb344',
            'medium'   => '#f5c518',
            'high'     => '#f76707',
            'critical' => '#d63939',
            default    => '#adb5bd',
        };
    }

    public static function badge(?string $level, ?int $score = null): string
    {
        if (!$level) {
            return '<span class="badge bg-secondary-lt">Sin evaluar</span>';
        }
        $label = self::levels()[$level] ?? $level;
        $fg    = $level === 'medium' ? '#1d273b' : '#fff';
        return sprintf(
            '<span class="badge" style="background:%s;color:%s">%s%s</span>',
            self::color($level),
            $fg,
            htmlescape($label),
            $score ? ' (' . $score . ')' : ''
        );
    }

    public static function treatments(): array
    {
        return ['' => 'Sin decisión', 'mitigate' => 'Mitigar', 'transfer' => 'Transferir', 'avoid' => 'Evitar', 'accept' => 'Aceptar'];
    }

    public static function statuses(): array
    {
        return ['identified' => 'Identificado', 'assessed' => 'Evaluado', 'in_treatment' => 'En tratamiento',
                'accepted' => 'Aceptado', 'closed' => 'Cerrado'];
    }

    public static function effectiveness(): array
    {
        return ['' => '-', 'none' => 'Inexistente', 'low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta'];
    }

    public static function velocities(): array
    {
        return ['' => '-', 'immediate' => 'Inmediata (días)', 'short' => 'Corta (semanas)', 'medium' => 'Media (meses)', 'long' => 'Larga (más de un año)'];
    }

    public static function trends(): array
    {
        return ['' => '-', 'up' => 'En aumento', 'stable' => 'Estable', 'down' => 'En disminución'];
    }

    public static function actionStatuses(): array
    {
        return ['planned' => 'Planificada', 'in_progress' => 'En curso', 'done' => 'Realizada', 'cancelled' => 'Cancelada'];
    }

    /**
     * Posición del riesgo residual respecto del apetito/tolerancia de su categoría.
     * @return array{0:string,1:string} [clave, etiqueta]
     */
    public static function appetitePosition(?string $level, ?string $appetite, ?string $tolerance): array
    {
        if (!$level || (!$appetite && !$tolerance)) {
            return ['undefined', 'Sin apetito definido'];
        }
        if ($tolerance && self::rank($level) > self::rank($tolerance)) {
            return ['above_tolerance', 'Sobre la tolerancia'];
        }
        if ($appetite && self::rank($level) > self::rank($appetite)) {
            return ['above_appetite', 'Sobre el apetito'];
        }
        return ['within', 'Dentro del apetito'];
    }

    public static function appetiteBadge(array $pos): string
    {
        $class = ['within' => 'bg-green-lt', 'above_appetite' => 'bg-orange-lt', 'above_tolerance' => 'bg-red-lt'][$pos[0]] ?? 'bg-secondary-lt';
        return sprintf('<span class="badge %s">%s</span>', $class, htmlescape($pos[1]));
    }

    public static function money(?float $value): string
    {
        if ($value === null) {
            return '-';
        }
        return 'ARS ' . number_format($value, 0, ',', '.');
    }
}

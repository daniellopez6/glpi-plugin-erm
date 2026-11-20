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

namespace GlpiPlugin\Erm\Tests\Unit;

use GlpiPlugin\Erm\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /**
     * @return array<string, array{0:string, 1:string|false}>
     */
    public static function decimals(): array
    {
        return [
            'integer'           => ['2500000', '2500000'],
            'dot decimal'       => ['1500000.5', '1500000.5'],
            'comma decimal'     => ['1500000,5', '1500000.5'],
            'es_AR thousands'   => ['1.500.000,50', '1500000.50'],
            'en thousands'      => ['1,500,000.50', '1500000.50'],
            'dot thousands'     => ['1.500.000', '1500000'],
            'spaces'            => [' 1 500 ', '1500'],
            'negative'          => ['-3,5', '-3.5'],
            'empty'             => ['', 'NULL'],
            'text'              => ['mucho', false],
            'mixed'             => ['12abc', false],
        ];
    }

    #[DataProvider('decimals')]
    public function testDecimal(string $input, string|false $expected): void
    {
        $this->assertSame($expected, Validator::decimal($input));
    }

    /**
     * @return array<string, array{0:string, 1:string|false}>
     */
    public static function dates(): array
    {
        return [
            'valid'          => ['2026-11-20', '2026-11-20'],
            'datetime'       => ['2026-11-20 10:30:00', '2026-11-20'],
            'empty'          => ['', 'NULL'],
            'invalid month'  => ['2026-13-01', false],
            'invalid day'    => ['2026-02-30', false],
            'other format'   => ['20/11/2026', false],
        ];
    }

    #[DataProvider('dates')]
    public function testDate(string $input, string|false $expected): void
    {
        $this->assertSame($expected, Validator::date($input));
    }

    public function testRange(): void
    {
        $this->assertSame(3, Validator::range('3', 0, 5));
        $this->assertSame(0, Validator::range(9, 0, 5));
        $this->assertSame(0, Validator::range(-1, 0, 5));
    }
}

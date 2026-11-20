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

use GlpiPlugin\Erm\Numbering;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NumberingTest extends TestCase
{
    public function testFirstCodeOfAnEntity(): void
    {
        $this->assertSame('RC-001', Numbering::next([], 'RC'));
    }

    public function testNextCodeIgnoresOtherPrefixesAndManualCodes(): void
    {
        $codes = ['FIN-001', 'FIN-007', 'RC-099', 'FIN-ABC', 'FIN-002', 'CUSTOM'];
        $this->assertSame('FIN-008', Numbering::next($codes, 'FIN'));
    }

    public function testNumberingGoesBeyondThreeDigits(): void
    {
        $this->assertSame('RC-1000', Numbering::next(['RC-999'], 'RC'));
    }

    /**
     * @return array<string, array{0:?string, 1:string}>
     */
    public static function prefixes(): array
    {
        return [
            'lowercase'      => ['fin', 'FIN'],
            'invalid chars'  => ['C.D.V!', 'CDV'],
            'dashes trimmed' => ['-ACME-', 'ACME'],
            'too long'       => ['ABCDEFGHIJKLMNOP', 'ABCDEFGHIJ'],
            'empty'          => ['', 'RC'],
            'null'           => [null, 'RC'],
        ];
    }

    #[DataProvider('prefixes')]
    public function testPrefixNormalization(?string $input, string $expected): void
    {
        $this->assertSame($expected, Numbering::normalizePrefix($input));
    }
}

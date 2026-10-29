<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ShiftColorConverter;
use Tests\TestCase;

/**
 * シフトパターンの色（Tailwindのクラス名）を、Excelで使える色コードにする。
 */
class ShiftColorConverterTest extends TestCase
{
    private ShiftColorConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new ShiftColorConverter;
    }

    /**
     * @test
     */
    public function tailwindのクラス名を色コードにする(): void
    {
        $this->assertSame('#DBEAFE', $this->converter->toHex('bg-blue-100', '#000000'));
        $this->assertSame('#1E40AF', $this->converter->toHex('text-blue-800', '#000000'));
        $this->assertSame('#CCFBF1', $this->converter->toHex('bg-teal-100', '#000000'));
        $this->assertSame('#FEF9C3', $this->converter->toHex('bg-yellow-100', '#000000'));
        $this->assertSame('#FFFFFF', $this->converter->toHex('bg-white', '#000000'));
        $this->assertSame('#000000', $this->converter->toHex('text-black', '#FFFFFF'));
    }

    /**
     * @test
     */
    public function 色コードはそのまま使う(): void
    {
        $this->assertSame('#FFEED4', $this->converter->toHex('#ffeed4', '#000000'));
        $this->assertSame('#AABBCC', $this->converter->toHex('#abc', '#000000'));
    }

    /**
     * @test
     */
    public function 解釈できない値は代わりの色にする(): void
    {
        $this->assertSame('#123456', $this->converter->toHex(null, '#123456'));
        $this->assertSame('#123456', $this->converter->toHex('', '#123456'));
        $this->assertSame('#123456', $this->converter->toHex('bg-unknown-100', '#123456'));
        $this->assertSame('#123456', $this->converter->toHex('bg-blue-150', '#123456'));
        $this->assertSame('#123456', $this->converter->toHex('rounded-lg', '#123456'));
    }
}

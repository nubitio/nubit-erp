<?php

declare(strict_types=1);

namespace App\Tests\Billing\Shared\Service;

use App\Billing\Shared\Service\AmountFormatterService;
use PHPUnit\Framework\TestCase;

final class AmountFormatterServiceTest extends TestCase
{
    private AmountFormatterService $service;

    protected function setUp(): void
    {
        $this->service = new AmountFormatterService();
    }

    public function testFormatsWholeAmountInWords(): void
    {
        $result = $this->service->formatAmountInWords(100.00);

        self::assertStringStartsWith('SON ', $result);
        self::assertStringContainsString('SOLES', $result);
        // 100 → "cien"
        self::assertStringContainsStringIgnoringCase('cien', $result);
    }

    public function testFormatsAmountWithCentavos(): void
    {
        $result = $this->service->formatAmountInWords(50.75);

        self::assertStringContainsString('75 / 100', $result);
        self::assertStringContainsString('SOLES', $result);
    }

    public function testUsesCustomCurrencyLabel(): void
    {
        $result = $this->service->formatAmountInWords(10.00, 'DOLARES');

        self::assertStringContainsString('DOLARES', $result);
    }

    public function testFormatsZeroAmount(): void
    {
        $result = $this->service->formatAmountInWords(0.00);

        self::assertStringStartsWith('SON ', $result);
        self::assertStringContainsString('00 / 100', $result);
    }

    public function testOutputIsUppercase(): void
    {
        $result = $this->service->formatAmountInWords(5.00);

        self::assertSame($result, mb_strtoupper($result));
    }
}

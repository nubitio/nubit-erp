<?php

declare(strict_types=1);

namespace App\Tests\Billing\Domain;

use App\Billing\Receipt\Domain\ReceiptType;
use App\Billing\Sale\Void\SunatVoidResponse;
use App\Billing\Shared\Enum\ChargeType;
use App\Billing\Shared\Enum\OperationType;
use App\Billing\Shared\ErrorMessages;
use App\Billing\Shared\Service\AmountFormatterService;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use App\Billing\Sunat\Model\Response\SummaryResponse;
use PHPUnit\Framework\TestCase;

final class EInvoicingDomainTest extends TestCase
{
    // ── ChargeType enum ──────────────────────────────────────────────────────

    public function testChargeTypeValues(): void
    {
        self::assertSame('46', ChargeType::CONSUMPTION_SURCHARGE->value);
        self::assertSame('02', ChargeType::DISCOUNT->value);
    }

    public function testChargeTypeFromString(): void
    {
        self::assertSame(ChargeType::DISCOUNT, ChargeType::from('02'));
    }

    // ── OperationType enum ───────────────────────────────────────────────────

    public function testOperationTypeValues(): void
    {
        self::assertSame('0101', OperationType::SALE->value);
        self::assertSame('1001', OperationType::DETRACTION_SALE->value);
    }

    // ── ReceiptType enum ─────────────────────────────────────────────────────

    public function testReceiptTypeValues(): void
    {
        self::assertSame('01', ReceiptType::FACTURA->value);
        self::assertSame('03', ReceiptType::BOLETA->value);
        self::assertSame('07', ReceiptType::NOTA_CREDITO->value);
        self::assertSame('08', ReceiptType::NOTA_DEBITO->value);
    }

    public function testReceiptTypeAllCasesPresent(): void
    {
        self::assertCount(4, ReceiptType::cases());
    }

    // ── ErrorMessages constants ──────────────────────────────────────────────

    public function testErrorMessagesAreNonEmpty(): void
    {
        self::assertNotEmpty(ErrorMessages::SALE_NOT_EXISTS);
        self::assertNotEmpty(ErrorMessages::VOID_SUCCESSFUL);
        self::assertNotEmpty(ErrorMessages::TAX_DOCUMENT_NOT_FOUND);
        self::assertNotEmpty(ErrorMessages::NO_SERIES_CONFIGURED);
    }

    // ── SunatVoidResponse ─────────────────────────────────────────────────────

    public function testSunatVoidResponseGetCorrelation(): void
    {
        $raw = $this->createStub(SummaryResponse::class);
        $r = new SunatVoidResponse(
            rawResponse: $raw,
            name: 'RC-20240115-001-1',
            ticket: 'T-12345',
            success: true,
            message: null,
        );

        self::assertSame('1', $r->getCorrelation());
        self::assertSame('RC-20240115-001-1', $r->name);
        self::assertTrue($r->success);
        self::assertNull($r->message);
    }

    public function testSunatVoidResponseCorrelationNullWhenNameHasFewerParts(): void
    {
        $raw = $this->createStub(SummaryResponse::class);
        $r = new SunatVoidResponse(
            rawResponse: $raw,
            name: 'RC-short',
            ticket: 'T-0',
            success: false,
            message: 'Error',
        );

        self::assertNull($r->getCorrelation());
    }

    // ── AmountFormatterService ────────────────────────────────────────────────

    public function testFormatAmountInWordsContainsSon(): void
    {
        $svc = new AmountFormatterService();
        $result = $svc->formatAmountInWords(100.50);

        self::assertStringStartsWith('SON ', $result);
        self::assertStringContainsString('SOLES', $result);
    }

    public function testFormatAmountInWordsIsUppercase(): void
    {
        $svc = new AmountFormatterService();
        $result = $svc->formatAmountInWords(25.00);

        self::assertSame(mb_strtoupper($result), $result);
    }

    public function testFormatAmountInWordsCustomCurrency(): void
    {
        $svc = new AmountFormatterService();
        $result = $svc->formatAmountInWords(10.00, 'DÓLARES');

        self::assertStringContainsString('DÓLARES', $result);
    }
}

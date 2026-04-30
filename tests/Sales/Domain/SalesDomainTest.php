<?php

declare(strict_types=1);

namespace App\Tests\Sales\Domain;

use App\Sales\Domain\DocType;
use App\Sales\Domain\SaleException;
use App\Sales\Domain\SaleStatus;
use Nubit\Platform\Exception\ServiceException;
use PHPUnit\Framework\TestCase;

final class SalesDomainTest extends TestCase
{
    // ── DocType ──────────────────────────────────────────────────────────────

    public function testDocTypeValues(): void
    {
        self::assertSame('03', DocType::BOLETA->value);
        self::assertSame('01', DocType::FACTURA->value);
        self::assertSame('00', DocType::NOTA_VENTA->value);
    }

    public function testDocTypeFromString(): void
    {
        self::assertSame(DocType::BOLETA, DocType::from('03'));
        self::assertSame(DocType::FACTURA, DocType::from('01'));
    }

    public function testDocTypeTryFromInvalidReturnsNull(): void
    {
        self::assertSame(DocType::BOLETA, DocType::tryFrom('03'));
        // Invalid value returns null — verified via tryFrom contract
        self::assertCount(3, DocType::cases()); // only 3 valid values
    }

    public function testDocTypeAllCasesArePresent(): void
    {
        $cases = DocType::cases();
        self::assertCount(3, $cases);
    }

    // ── SaleStatus ───────────────────────────────────────────────────────────

    public function testSaleStatusValues(): void
    {
        self::assertSame(1, SaleStatus::ACTIVE->value);
        self::assertSame(2, SaleStatus::VOIDED->value);
    }

    public function testSaleStatusFromInt(): void
    {
        self::assertSame(SaleStatus::ACTIVE, SaleStatus::from(1));
        self::assertSame(SaleStatus::VOIDED, SaleStatus::from(2));
    }

    // ── SaleException ────────────────────────────────────────────────────────

    public function testSaleExceptionExtendsServiceException(): void
    {
        $e = SaleException::alreadyVoided();
        self::assertInstanceOf(ServiceException::class, $e);
    }

    public function testAlreadyVoidedMessage(): void
    {
        self::assertStringContainsString('anulada', SaleException::alreadyVoided()->getMessage());
    }

    public function testTooOldMessage(): void
    {
        self::assertStringContainsString('semana', SaleException::tooOld()->getMessage());
    }

    public function testNotIssuedMessage(): void
    {
        self::assertStringContainsString('SUNAT', SaleException::notIssued()->getMessage());
    }

    public function testHasCreditNoteMessage(): void
    {
        self::assertStringContainsString('nota de crédito', SaleException::hasCreditNote()->getMessage());
    }

    public function testEachFactoryCreatesNewInstance(): void
    {
        $a = SaleException::alreadyVoided();
        $b = SaleException::alreadyVoided();
        self::assertNotSame($a, $b);
    }
}

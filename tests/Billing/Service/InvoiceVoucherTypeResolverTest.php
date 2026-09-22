<?php

declare(strict_types=1);

namespace App\Tests\Billing\Service;

use App\Billing\Service\InvoiceVoucherTypeResolver;
use App\Sales\Domain\DocType;
use App\Sales\Domain\RequestedVoucherType;
use App\Settings\Enum\TaxRegime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceVoucherTypeResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{TaxRegime, ?RequestedVoucherType, string, string}>
     */
    public static function voucherTypes(): iterable
    {
        yield 'nrus always uses boleta' => [TaxRegime::NRUS, RequestedVoucherType::FACTURA, '20123456789', DocType::BOLETA->value];
        yield 'explicit factura' => [TaxRegime::GENERAL, RequestedVoucherType::FACTURA, '', DocType::FACTURA->value];
        yield 'explicit boleta' => [TaxRegime::GENERAL, RequestedVoucherType::BOLETA, '20123456789', DocType::BOLETA->value];
        yield 'ruc infers factura' => [TaxRegime::GENERAL, RequestedVoucherType::AUTO, '20123456789', DocType::FACTURA->value];
        yield 'missing ruc infers boleta' => [TaxRegime::GENERAL, null, '', DocType::BOLETA->value];
    }

    /**
     * @param ?RequestedVoucherType $requestedType
     */
    #[DataProvider('voucherTypes')]
    public function testResolvesDocumentType(
        TaxRegime $taxRegime,
        ?RequestedVoucherType $requestedType,
        string $customerRuc,
        string $expected,
    ): void {
        static::assertSame($expected, InvoiceVoucherTypeResolver::resolve($taxRegime, $requestedType, $customerRuc));
    }
}

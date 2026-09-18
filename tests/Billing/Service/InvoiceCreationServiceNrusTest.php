<?php

declare(strict_types=1);

namespace App\Tests\Billing\Service;

use App\Billing\Service\InvoiceCreationService;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SaleCurrencyResolver;
use App\Settings\Enum\TaxRegime;
use App\Settings\Service\TenantSettingsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class InvoiceCreationServiceNrusTest extends TestCase
{
    public function testNrusBoletaUsesExoneratedAffectationAndZeroIgv(): void
    {
        $sale = new SaleRecord()
            ->setId('sale-nrus-1')
            ->setFecha(new \DateTimeImmutable('now'))
            ->setDateCreated(new \DateTimeImmutable('now'))
            ->setIgv('0')
            ->setDescuento('0')
            ->setTotal('48.00');

        $item = new SaleItemRecord();
        $item->setQty('1');
        $item->setUnitPrice('48.00');
        $item->setNetAmount('48.00');
        $item->setGrossAmount('48.00');
        $item->setNameSnapshot('Producto gravado');
        $sale->addSaleItemRecord($item);

        $insertedDocument = null;
        $insertedLine = null;
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql): string|false|int {
            return match (true) {
                str_contains($sql, 'almacen') => 'local-1',
                str_contains($sql, 'tax_document_id') && str_contains($sql, 'FOR UPDATE') => false,
                str_contains($sql, 'local_comprobante') => 'B001',
                str_contains($sql, 'IFNULL') || str_contains($sql, 'MAX(') => '1',
                str_contains($sql, 'UUID') => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                str_contains($sql, 'FROM sales') && str_contains($sql, 'WHERE id') => 'sale-nrus-1',
                str_contains($sql, 'information_schema') => 1,
                default => false,
            };
        });
        $connection->method('insert')->willReturnCallback(static function (string $table, array $data) use (
            &$insertedDocument,
            &$insertedLine,
        ): int {
            if ($table === 'tax_document') {
                $insertedDocument = $data;
            }
            if ($table === 'tax_document_line') {
                $insertedLine = $data;
            }

            return 1;
        });
        $connection->method('executeStatement')->willReturn(1);

        $resolver = $this->createStub(SaleCurrencyResolver::class);
        $resolver->method('resolveForSaleId')->willReturn(['currency' => 'PEN', 'exchange_rate' => '1.00']);

        $settings = $this->createStub(TenantSettingsService::class);
        $settings->method('getTaxRegime')->willReturn(TaxRegime::NRUS);
        $settings->method('getIgv')->willReturn(0.0);

        $result = (new InvoiceCreationService($connection, $resolver, $settings))->createForSale($sale);

        self::assertTrue($result['success']);
        self::assertSame('03', $insertedDocument['document_type'] ?? null);
        self::assertSame('0.00', $insertedDocument['igv_amount'] ?? null);
        self::assertSame('20', $insertedLine['tax_type'] ?? null);
        self::assertSame(0.0, $insertedLine['tax_percentage'] ?? null);
        self::assertSame('0.00', $insertedLine['tax_amount'] ?? null);
    }
}

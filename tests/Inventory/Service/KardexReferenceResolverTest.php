<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Service;

use App\Inventory\ApiResource\StockMovement;
use App\Inventory\Service\KardexReferenceResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class KardexReferenceResolverTest extends TestCase
{
    public function testResolvesSaleDocumentFromTaxDocument(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'sale-1',
                    'sale_series' => null,
                    'sale_number' => null,
                    'tax_series' => 'B001',
                    'tax_number' => '123',
                ],
            ]);

        $resolver = new KardexReferenceResolver($connection);
        $reference = $resolver->resolve('LEGACY_MOVIMIENTO_ITEM', 'sale-1:SALE_OUT:1');

        self::assertSame('B001-123', $reference);
    }

    public function testResolvesPurchaseSupplierDocument(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'purchase-1',
                    'supplier_doc_series' => 'F001',
                    'supplier_doc_number' => '45',
                    'receipt_no' => 12,
                ],
            ]);

        $resolver = new KardexReferenceResolver($connection);
        $reference = $resolver->resolve('PURCHASE_ITEM', 'purchase-1:2');

        self::assertSame('F001-45', $reference);
    }

    public function testUsesReceiptNumberWhenSupplierDocumentIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'purchase-legacy',
                    'supplier_doc_series' => null,
                    'supplier_doc_number' => null,
                    'receipt_no' => 42,
                ],
            ]);

        $resolver = new KardexReferenceResolver($connection);
        $reference = $resolver->resolve('PURCHASE_ITEM', 'purchase-legacy:1');

        self::assertSame('RC-000042', $reference);
    }

    public function testResolvesSaleByLegacyMovimientoId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'sale-v2-id',
                    'legacy_movimiento_id' => 'sale-legacy-id',
                    'sale_series' => '001',
                    'sale_number' => '1007',
                    'tax_series' => null,
                    'tax_number' => null,
                ],
            ]);

        $resolver = new KardexReferenceResolver($connection);
        $reference = $resolver->resolve('LEGACY_MOVIMIENTO_ITEM', 'sale-legacy-id:SALE_OUT:1');

        self::assertSame('001-1007', $reference);
    }

    public function testResolvesSaleNotaDeVentaFromSalesSeriesNumber(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'sale-nota',
                    'legacy_movimiento_id' => 'sale-nota-legacy',
                    'sale_series' => '001',
                    'sale_number' => '1006',
                    'tax_series' => null,
                    'tax_number' => null,
                ],
            ]);

        $resolver = new KardexReferenceResolver($connection);
        $reference = $resolver->resolve('LEGACY_MOVIMIENTO_ITEM', 'sale-nota-legacy:SALE_OUT:1');

        self::assertSame('001-1006', $reference);
    }

    public function testLeavesSourceReferenceNullWhenSaleHasNoDocument(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'sale-missing-doc',
                    'sale_series' => null,
                    'sale_number' => null,
                    'tax_series' => null,
                    'tax_number' => null,
                ],
            ]);

        $movement = new StockMovement();
        $movement->sourceType = 'LEGACY_MOVIMIENTO_ITEM';
        $movement->sourceId = 'sale-missing-doc:SALE_OUT:1';

        $resolver = new KardexReferenceResolver($connection);
        $resolver->applyToMovements([$movement]);

        self::assertNull($movement->sourceReference);
    }

    public function testDoesNotUseUuidFallbackForPurchases(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $resolver = new KardexReferenceResolver($connection);
        $reference = $resolver->resolve('PURCHASE_ITEM', '019eaec4-ed00-7651-91fd-993694369ff1:1');

        self::assertNull($reference);
    }
}

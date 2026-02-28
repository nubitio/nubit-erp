<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Service;

use App\EInvoicing\Service\InvoiceCreationService;
use App\Entity\Sale;
use App\Service\SaleCurrencyResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class InvoiceCreationServiceTest extends TestCase
{
    public function testCreateForSaleFailsWhenSaleIdIsMissing(): void
    {
        $service = new InvoiceCreationService(
            $this->createMock(Connection::class),
            $this->createMock(SaleCurrencyResolver::class)
        );

        $result = $service->createForSale(new Sale());

        self::assertFalse($result['success']);
        self::assertSame('La venta no tiene un identificador válido.', $result['message']);
    }

    public function testCreateForSaleFailsWhenSaleAlreadyHasInvoice(): void
    {
        $sale = (new Sale())
            ->setId('sale-1')
            ->setHasInvoice(true)
            ->setInvoice('F001-20');

        $service = new InvoiceCreationService(
            $this->createMock(Connection::class),
            $this->createMock(SaleCurrencyResolver::class)
        );

        $result = $service->createForSale($sale);

        self::assertFalse($result['success']);
        self::assertSame('La venta ya tiene un comprobante emitido.', $result['message']);
    }

    public function testCreateForSaleFailsWhenDateExceedsAllowedWindow(): void
    {
        $sale = (new Sale())
            ->setId('sale-1')
            ->setFecha(new \DateTimeImmutable('-4 days'));

        $service = new InvoiceCreationService(
            $this->createMock(Connection::class),
            $this->createMock(SaleCurrencyResolver::class)
        );

        $result = $service->createForSale($sale);

        self::assertFalse($result['success']);
        self::assertSame('La fecha de la venta excede el límite permitido para emisión (3 días).', $result['message']);
    }

    public function testSyncInvoicedSaleStateUpdatesV2AndLegacyTablesWhenPresent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            static fn (string $sql, array $params): int => (($params['table_name'] ?? '') !== '') ? 1 : 0
        );
        $connection->expects(self::exactly(2))
            ->method('executeStatement');

        $service = new InvoiceCreationService(
            $connection,
            $this->createMock(SaleCurrencyResolver::class)
        );

        $this->invokePrivate($service, 'syncInvoicedSaleState', ['sale-1', 'F001-10', '100.00']);
    }

    /**
     * @param list<mixed> $args
     */
    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionObject($object);
        $refMethod = $reflection->getMethod($method);

        return $refMethod->invokeArgs($object, $args);
    }
}

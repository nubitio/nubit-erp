<?php

declare(strict_types=1);

namespace App\Tests\Billing\Service;

use App\Billing\Service\InvoiceCreationService;
use App\Contacts\Entity\Customer;
use App\Sales\Domain\RequestedVoucherType;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SaleCurrencyResolver;
use App\Settings\Enum\TaxRegime;
use App\Settings\Service\TenantSettingsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the Ventas module "Generar comprobante" action letting a
 * user pick Boleta/Factura at invoice time (createForSale's
 * $requestedVoucherTypeOverride param), instead of only at sale creation.
 */
final class InvoiceCreationServiceRequestedVoucherTypeOverrideTest extends TestCase
{
    public function testOverrideForcesBoletaForAnAutoResolvedRucCustomer(): void
    {
        // Sale has no explicit requestedVoucherType (AUTO) and a customer with an
        // 11-digit RUC — without an override this would resolve to Factura.
        $sale = $this->buildSaleForCustomerWithRuc('20123456789');

        $documentTypeInserted = null;
        $connection = $this->mockConnectionCapturingDocumentType($documentTypeInserted);

        $resolver = $this->createStub(SaleCurrencyResolver::class);
        $resolver->method('resolveForSaleId')->willReturn(['currency' => 'PEN', 'exchange_rate' => '1.00']);

        $tenantSettingsService = $this->createStub(TenantSettingsService::class);
        $tenantSettingsService->method('getTaxRegime')->willReturn(TaxRegime::GENERAL);

        $service = new InvoiceCreationService($connection, $resolver, $tenantSettingsService);
        $result = $service->createForSale($sale, RequestedVoucherType::BOLETA);

        static::assertTrue($result['success'], 'Invoice creation must succeed: ' . $result['message']);
        static::assertSame('03', $documentTypeInserted, 'Override must force Boleta even for an 11-digit RUC customer');
        static::assertSame(
            RequestedVoucherType::BOLETA,
            $sale->getRequestedVoucherType(),
            'The override must be persisted back onto the sale entity',
        );
    }

    private function mockConnectionCapturingDocumentType(?string &$documentTypeInserted): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params = []) {
                if (str_contains($sql, 'almacen')) {
                    return 'local-1';
                }
                if (str_contains($sql, 'tax_document_id') && str_contains($sql, 'FOR UPDATE')) {
                    return false;
                }
                if (str_contains($sql, 'local_comprobante')) {
                    return 'B001';
                }
                if (str_contains($sql, 'IFNULL') || str_contains($sql, 'MAX(')) {
                    return '1';
                }
                if (str_contains($sql, 'UUID')) {
                    return 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
                }
                if (str_contains($sql, 'FROM sales') && str_contains($sql, 'WHERE id')) {
                    return 'v2-sale-id-1';
                }
                if (str_contains($sql, 'information_schema')) {
                    return ($params['table_name'] ?? null) === 'sales' ? 1 : false;
                }

                return false;
            });

        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection
            ->method('insert')
            ->willReturnCallback(static function (string $table, array $data) use (&$documentTypeInserted): int {
                if ($table === 'tax_document') {
                    $documentTypeInserted = $data['document_type'] ?? null;
                }

                return 1;
            });
        $connection->method('executeStatement')->willReturn(1);

        return $connection;
    }

    private function buildSaleForCustomerWithRuc(string $ruc): SaleRecord
    {
        $now = new \DateTimeImmutable('now');
        $customer = new Customer()->setRuc($ruc);

        $sale = new SaleRecord()
            ->setId('sale-override-test-1')
            ->setFecha($now)
            ->setDateCreated($now)
            ->setIgv('18')
            ->setDescuento('0')
            ->setTotal('118.00')
            ->setCliente($customer);

        $item = new SaleItemRecord();
        $item->setQty('1');
        $item->setUnitPrice('118.00');
        $item->setNetAmount('118.00');
        $item->setGrossAmount('118.00');
        $item->setNameSnapshot('Test Item');
        $sale->addSaleItemRecord($item);

        return $sale;
    }
}

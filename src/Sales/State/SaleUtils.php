<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Sales\Domain\DocType;
use App\Sales\Entity\SaleRecord;
use App\Settings\Service\TenantSettingsService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Nubit\Platform\Exception\ServiceException;

readonly class SaleUtils
{
    public function __construct(
        private Connection $connection,
        private TenantSettingsService $tenantSettings,
    ) {}

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function getSaleSeries(string $docType, string $wareHouseId): string
    {
        $series = $this->fetchSeries($docType, $wareHouseId);

        // Internal notes of sale are not SUNAT documents. Auto-provision N001 so a
        // cashier can still ring up a sale when the branch was created without that series.
        if ($series === null && $docType === DocType::NOTA_VENTA->value) {
            $series = $this->provisionInternalSaleSeries($wareHouseId);
        }

        if ($series === null) {
            throw new ServiceException($this->missingSeriesMessage($docType));
        }

        return $series;
    }

    /**
     * @throws Exception
     */
    private function fetchSeries(string $docType, string $wareHouseId): ?string
    {
        /** @var false|string $series */
        $series = $this->connection->fetchOne('
            select
                series
            from local_comprobante lc
            inner join almacen a on a.local_id = lc.local_id
            where tipo_comprobante_id = ?
            and a.almacen_id = ?', [$docType, $wareHouseId]);

        return $series === false || $series === '' ? null : (string) $series;
    }

    /**
     * @throws Exception
     */
    private function provisionInternalSaleSeries(string $wareHouseId): ?string
    {
        /** @var false|string $branchId */
        $branchId = $this->connection->fetchOne(
            'SELECT local_id FROM almacen WHERE almacen_id = ?',
            [$wareHouseId],
        );
        if ($branchId === false || $branchId === '') {
            return null;
        }

        $voucherTypeExists = $this->connection->fetchOne(
            'SELECT codigo FROM tipo_comprobante WHERE codigo = ?',
            [DocType::NOTA_VENTA->value],
        );
        if ($voucherTypeExists === false) {
            return null;
        }

        $existing = $this->connection->fetchOne(
            'SELECT series FROM local_comprobante WHERE local_id = ? AND tipo_comprobante_id = ? LIMIT 1',
            [$branchId, DocType::NOTA_VENTA->value],
        );
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $this->connection->insert('local_comprobante', [
            'local_id' => $branchId,
            'tipo_comprobante_id' => DocType::NOTA_VENTA->value,
            'series' => 'N001',
            'is_active' => 1,
        ]);

        return 'N001';
    }

    private function missingSeriesMessage(string $docType): string
    {
        return match ($docType) {
            DocType::FACTURA->value => 'Esta sucursal no tiene serie de factura (F001). Configúrala en Configuración → Series de comprobantes.',
            DocType::BOLETA->value => 'Esta sucursal no tiene serie de boleta (B001). Configúrala en Configuración → Series de comprobantes.',
            DocType::NOTA_VENTA->value => 'Esta sucursal no tiene serie de nota de venta (N001). Configúrala en Configuración → Series de comprobantes.',
            default => 'Esta sucursal no tiene serie para el tipo de comprobante seleccionado. Configúrala en Configuración → Series de comprobantes.',
        };
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function getSaleNumber(string $series): string
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                'INSERT INTO sale_sequences (series, last_number) VALUES (:series, 1)
                 ON DUPLICATE KEY UPDATE last_number = last_number + 1',
                ['series' => $series],
            );
            /** @var false|string $number */
            $number = $this->connection->fetchOne('SELECT last_number FROM sale_sequences WHERE series = :series', [
                'series' => $series,
            ]);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw new ServiceException('No se pudo generar el número de comprobante.', 0, $e);
        }

        if ($number === false) {
            throw new ServiceException('No se encontró el número de comprobante.');
        }

        return strval($number);
    }

    /**
     * @throws Exception
     */
    public function fillSaleValues(SaleRecord $sale, DocType $docType = DocType::NOTA_VENTA): SaleRecord
    {
        $sale->setTipoId(2);
        if ($sale->getTipoPago()->getId() === 2) {
            $sale->setFormaPago(2);
        } else {
            $sale->setFormaPago(1);
        }
        $sale->setSerie($this->getSaleSeries($docType->value, $sale->getAlmacen()?->getId() ?: ''));
        $sale->setNumero($this->getSaleNumber($sale->getSerie()));

        $sale->setIgv((string) $this->tenantSettings->getIgv());
        $sale->setRecargoConsumo((int) $this->tenantSettings->getRc());

        return $sale;
    }
}

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
        /** @var false|string $series */
        $series = $this->connection->fetchOne('
            select
                series
            from local_comprobante lc
            inner join almacen a on a.local_id = lc.local_id
            where tipo_comprobante_id = ?
            and a.almacen_id = ?', [$docType, $wareHouseId]);

        if (!$series) {
            throw new ServiceException('No se encontró la serie para el tipo de comprobante y el local seleccionado.');
        }

        return strval($series);
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

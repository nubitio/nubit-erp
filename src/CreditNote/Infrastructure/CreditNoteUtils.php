<?php

namespace App\CreditNote\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class CreditNoteUtils
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function getCreditNoteSeries(string $saleId): string
    {
        $saleSeries = $this->connection->fetchOne('select serie from comprobante where movimiento_id = ?', [$saleId]);

        if (!$saleSeries) {
            throw new ServiceException('No se encontró la serie para la venta.');
        }

        /** @var false|string $creditNoteSeries */
        $creditNoteSeries = $this->connection->fetchOne(
            'select serie from local_comprobante where serie_asociada = ?',
            [$saleSeries]
        );

        if (!$creditNoteSeries) {
            throw new ServiceException('No se encontró la serie asociada para el tipo de comprobante.');
        }

        return strval($creditNoteSeries);
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function getCreditNoteNumber(string $series): int
    {
        /** @var false|int $number */
        $number = $this->connection->fetchOne(
            'select ifnull(max(cast(correlativo as int)), 0) + 1 as number from nota where serie = ?',
            [$series]
        );

        if (!$number) {
            throw new ServiceException('No se encontró el número de comprobante.');
        }

        return intval($number);
    }
}

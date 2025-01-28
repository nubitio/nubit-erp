<?php

namespace App\Sale\Infrastructure;

use App\CashboxNew\Domain\CashboxGetLastUseCase;
use App\Core\Application\Session\Security;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\SettingsService;
use App\Sale\Domain\DocType;
use App\Sale\Domain\Sale;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class SaleUtils
{
    public function __construct(
        private Connection $connection,
        private SettingsService $settings,
        private Security $security,
        private CashboxGetLastUseCase $cashboxGetLastUseCase,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function getSaleSeries(string $docType, string $wareHouseId): string
    {
        /** @var false|string $series */
        $series = $this->connection->fetchOne('
            select
                serie
            from local_comprobante lc
            inner join almacen a on a.local_id = lc.local_id
            where tipo_comprobante_id = ?
            and a.almacen_id = ?', [$docType, $wareHouseId]
        );

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
        /** @var false|string $number */
        $number = $this->connection->fetchOne('
            select
                ifnull(max(cast(numero as int)), 0) + 1 as numero
            from movimiento
            where serie = ?', [$series]
        );

        if (!$number) {
            throw new ServiceException('No se encontró el número de comprobante.');
        }

        return strval($number);
    }

    public function fillSaleValues(Sale $sale, DocType $docType = DocType::NOTA_VENTA): Sale
    {
        $sale->tipo_id = 2;
        $sale->serie = $this->getSaleSeries($docType->value, $sale->almacen?->almacen_id ?: '');
        $sale->numero = $this->getSaleNumber($sale->serie);
        $sale->caja_id = $sale->caja_id ?? $this->getCashBoxId();
        $sale->igv = intval($this->settings->get(Constants::TAX));
        $sale->recargo_consumo = intval($this->settings->get(Constants::CONSUMPTION_TAX));

        return $sale;
    }

    public function getCashBoxId(): ?string
    {
        $branchId = $this->security->connectedUser()?->branchId;
        $userId = $this->security->connectedUser()?->userId;

        if (!$branchId || !$userId) {
            return null;
        }

        $cashBox = $this->cashboxGetLastUseCase->__invoke($branchId, $userId);

        return $cashBox?->caja_id;
    }
}

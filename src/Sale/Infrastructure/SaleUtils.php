<?php

namespace App\Sale\Infrastructure;

use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\SettingsService;
use App\Sale\Domain\DocType;
use App\Sale\Domain\Sale;
use Doctrine\DBAL\Connection;

readonly class SaleUtils
{
    public function __construct(
        private Connection      $connection,
        private SettingsService $settings,
    )
    {
    }

    public function getSaleSeries(string $docType, string $wareHouseId): string
    {
        return $this->connection->fetchOne('
            select
                serie
            from local_comprobante lc
            inner join almacen a on a.local_id = lc.local_id
            where tipo_comprobante_id = ?
            and a.almacen_id = ?', [$docType, $wareHouseId]
        );
    }

    public function getSaleNumber(string $series): string
    {
        return $this->connection->fetchOne('
            select
                ifnull(max(cast(numero as int)), 0) + 1 as numero
            from movimiento
            where serie = ?', [$series]
        );
    }

    public function fillSaleValues(Sale $sale, DocType $docType = DocType::NOTA_VENTA): Sale
    {
        $sale->tipo_id = 2;
        $sale->estado = 1;
        $sale->serie = $this->getSaleSeries($docType->value, $sale->almacen->almacen_id);
        $sale->numero = $this->getSaleNumber($sale->serie);
        $sale->igv = (int)$this->settings->get(Constants::TAX);
        $sale->recargo_consumo = (int)$this->settings->get(Constants::CONSUMPTION_TAX);
        $sale->date_created = new \DateTime();

        return $sale;
    }
}

<?php

namespace App\Sale\Infrastructure;

use App\Core\Infrastructure\Service\SettingsService;
use App\Sale\Domain\DocType;
use App\Sale\Domain\Sale;
use App\Core\Domain\Util\Constants;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SaleUtils
{
    private Connection $connection;
    private SettingsService $settings;

    public function __construct(Connection $connection, SettingsService $settings)
    {
        $this->connection = $connection;
        $this->settings = $settings;
    }

    public function getSaleSeries(string $docType, string $localId): string
    {
        return $this->connection->fetchOne('
          select
            serie
          from local_comprobante
          where tipo_comprobante_id = ?
          and local_id = ?', [$docType, $localId]
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

    public function fillSaleValues(Sale $sale, DocType $docType, SessionInterface $session): Sale
    {
        $sale->tipo_id = 2;
        $sale->estado = 1;
        $sale->serie = $this->getSaleSeries($docType->value, $session->get('local_id'));
        $sale->numero = $this->getSaleNumber($sale->serie);
        $sale->igv = $this->settings->get(Constants::TAX);
        $sale->recargo_consumo = $this->settings->get(Constants::CONSUMPTION_TAX);
        $sale->date_created = new \DateTime();
        $sale->created_by = $session->get('usuario_id');

        return $sale;
    }
}

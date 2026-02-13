<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Application\Session\Security;
use App\Core\Exception\ServiceException;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Entity\CashShift;
use App\Entity\Sale;
use App\Enum\CashShiftStatus;
use App\Sale\Domain\DocType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

readonly class SaleUtils
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private SettingsService $settings,
        private Security $security,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function getSaleSeries(string $docType, string $wareHouseId): string
    {
        /** @var false|string $series */
        $series = $this->connection->fetchOne(
            '
            select
                serie
            from local_comprobante lc
            inner join almacen a on a.local_id = lc.local_id
            where tipo_comprobante_id = ?
            and a.almacen_id = ?',
            [$docType, $wareHouseId]
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
        $number = $this->connection->fetchOne(
            '
            select
                ifnull(max(cast(numero as int)), 0) + 1 as numero
            from movimiento
            where serie = ?',
            [$series]
        );

        if (!$number) {
            throw new ServiceException('No se encontró el número de comprobante.');
        }

        return strval($number);
    }

    /**
     * @throws Exception
     */
    public function fillSaleValues(
        Sale $sale,
        DocType $docType = DocType::NOTA_VENTA,
    ): Sale {
        $sale->setTipoId(2);
        if ($sale->getTipoPago()->getId() === 2) {
            $sale->setFormaPago(2);
        } else {
            $sale->setFormaPago(1);
        }
        $sale->setSerie(
            $this->getSaleSeries(
                $docType->value,
                $sale->getAlmacen()?->getId() ?: ''
            )
        );
        $sale->setNumero($this->getSaleNumber($sale->getSerie()));

        if ($sale->getCajaId() === null) {
            $sale->setCajaId($this->getCashBoxId());
        }

        $sale->setIgv(floatval($this->settings->get(Constants::TAX)));
        $sale->setRecargoConsumo(
            intval(
                $this->settings->get(Constants::CONSUMPTION_TAX)
            )
        );

        return $sale;
    }

    private function getCashBoxId(): ?int
    {
        $user = $this->security->connectedUser();

        $cashShift = $this->entityManager->getRepository(CashShift::class)
            ->findOneBy([
                'status' => CashShiftStatus::OPEN,
                'openedBy' => $user->userId,
            ]);

        return $cashShift?->getCashRegister()->getId();
    }
}

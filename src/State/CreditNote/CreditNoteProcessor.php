<?php

declare(strict_types=1);

namespace App\State\CreditNote;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Exception\ServiceException;
use App\Core\Security\Auth\BranchContextService;
use App\Entity\CreditNote;
use App\Repository\BranchRepository;
use App\Service\CreditNoteService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class CreditNoteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private EntityManagerInterface $entityManager,
        private CreditNoteService $creditNoteService,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param CreditNote $data
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($data->getId() && $data->isSentToSunat()) {
            if ($operation instanceof DeleteOperationInterface) {
                throw new ServiceException('No se puede eliminar una nota de crédito enviada a SUNAT.');
            }

            throw new ServiceException('No se puede editar una nota de crédito enviada a SUNAT.');
        }

        if ($operation instanceof DeleteOperationInterface) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if (!$data->getId()) {
            $this->applyDefaults($data);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function applyDefaults(CreditNote $creditNote): void
    {
        $creditNote->setFecha(new DateTime());
        $creditNote->setTipoId('07');
        $creditNote->setSerie($this->resolveSeries($creditNote));
        $creditNote->setCorrelativo(
            $this->creditNoteService->getNextCorrelativo($creditNote->getSerie())
        );
        $creditNote->setDocAfectado($creditNote->getSale()->getInvoice());
        $creditNote->setTotal($creditNote->getSale()->getTotal());

        // Set branch from current context
        $branchId = $this->branchContextService->getCurrentBranchId();
        if ($branchId !== null) {
            $branch = $this->branchRepository->find($branchId);
            $creditNote->setBranch($branch);
        }
    }

    private function resolveSeries(CreditNote $creditNote): string
    {
        $sale = $creditNote->getSale();
        $warehouseId = $sale->getAlmacen()?->getId();
        if ($warehouseId === null || $warehouseId === '') {
            throw new ServiceException('No se encontró el almacén asociado a la venta.');
        }

        $connection = $this->entityManager->getConnection();
        $localId = $connection->fetchOne(
            'select local_id from almacen where almacen_id = ?',
            [$warehouseId]
        );

        if (!$localId) {
            throw new ServiceException('No se encontró el local asociado al almacén.');
        }

        $voucherSeries = $connection->fetchOne(
            'select ifnull(i.serie, m.serie)
             from movimiento m
             left join comprobante i on i.movimiento_id = m.movimiento_id
             where m.movimiento_id = ?',
            [$sale->getId()]
        );

        $series = $connection->fetchOne(
            'select serie
             from local_comprobante
             where local_id = ?
               and tipo_comprobante_id = ?
               and (serie_asociada = ? or serie_asociada is null)
             order by (serie_asociada = ?) desc
             limit 1',
            [$localId, '07', $voucherSeries, $voucherSeries]
        );

        if (!$series) {
            throw new ServiceException(
                'No hay serie de nota de crédito configurada para el local y la serie asociada.'
            );
        }

        return strval($series);
    }
}

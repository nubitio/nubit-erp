<?php

declare(strict_types=1);

namespace App\State\TaxDocument;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Exception\ServiceException;
use App\Core\Security\Auth\BranchContextService;
use App\Entity\TaxDocument;
use App\Repository\BranchRepository;
use App\Repository\TaxDocumentRepository;
use DateTime;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class TaxDocumentProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
        private TaxDocumentRepository $taxDocumentRepository,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param TaxDocument $data
     *
     * @throws ServiceException
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $isNew = !$data->getId();

        if ($operation instanceof DeleteOperationInterface) {
            if ($data->isProcessed()) {
                throw new ServiceException('No se puede eliminar un documento procesado.');
            }

            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($isNew && $data->getReasonCode() != null) {
            $data->setDocumentType('07');
            $data->setSale($data->getRelatedTaxDocument()->getSale());
            $data->setAffectedDocument($data->getSale()->getInvoice());
            $data->setCustomer($data->getSale()->getCliente());
            $data->setTotal($data->getSale()->getTotal());
            $branchId = $this->branchContextService->getCurrentBranchId();
            $seriesResult = $this->taxDocumentRepository->getTaxDocumentSeriesByBranch($branchId, '07');
            $number = $this->taxDocumentRepository->getNextSequenceNumber($branchId, '07');
            if ($seriesResult->associatedSeries != substr($data->getAffectedDocument(), 0, 4)) {
                throw new ServiceException(
                    'La serie de la nota de crédito no coincide con la serie del documento afectado.'
                );
            }
            $data->setSeries($seriesResult->series);
            $data->setNumber((string)$number);
        }

        if ($isNew) {
            $data->setCreatedAt(new DateTime());

            // Set the branch from the current user's session
            $branchId = $this->branchContextService->getCurrentBranchId();
            if ($branchId !== null) {
                $branch = $this->branchRepository->find($branchId);
                $data->setBranch($branch);
            }
        } else {
            if ($data->isProcessed()) {
                throw new ServiceException('No se puede actualizar un documento procesado.');
            }
            $data->setUpdatedAt(new DateTime());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

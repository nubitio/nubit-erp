<?php

declare(strict_types=1);

namespace App\Billing\Despatch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Billing\Despatch\Entity\DespatchGuide;
use App\Billing\Despatch\Repository\DespatchGuideRepository;
use App\Identity\Auth\BranchContextService;
use App\Organization\Repository\BranchRepository;
use Nubit\Platform\Exception\ServiceException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<DespatchGuide, DespatchGuide>
 */
readonly class DespatchGuideProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<DespatchGuide, DespatchGuide> $persistProcessor
     */
    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(
            service: 'api_platform.doctrine.orm.state.persist_processor',
        )]
        private ProcessorInterface $persistProcessor,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
        private DespatchGuideRepository $guideRepository,
    ) {}

    /**
     * @param DespatchGuide $data
     *
     * @throws ServiceException
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isNew = $data->getId() === null;

        if ($isNew) {
            if (trim($data->getSeries()) === '') {
                throw new ServiceException('Debe indicar la serie de la guía de remisión.');
            }
            if ($data->getLines()->isEmpty()) {
                throw new ServiceException('La guía de remisión debe tener al menos un ítem.');
            }

            $branchId = $this->branchContextService->getCurrentBranchId();
            if ($branchId !== null) {
                $data->setBranch($this->branchRepository->find($branchId));
            }

            $next = $this->guideRepository->allocateNextNumber($data->getSeries());
            $data->setNumber((string) $next);
        } else {
            if ($data->isProcessed()) {
                throw new ServiceException('No se puede modificar una guía ya enviada a SUNAT.');
            }
            $data->setUpdatedAt(new \DateTime());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

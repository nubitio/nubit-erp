<?php

namespace App\State\Sale;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Domain\Exception\ServiceException;
use App\Entity\CashShift;
use App\Entity\SaleEntity;
use App\Enum\CashShiftStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class SaleProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private SaleUtils $saleUtils,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param SaleEntity $data
     *
     * @throws ServiceException
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof DeleteOperationInterface) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if (!$data->id && $data->tipo_pago) {
            $user = $this->security->connectedUser();
            $cashShift = $this->entityManager->getRepository(CashShift::class)
                ->findOneBy([
                    'status' => CashShiftStatus::OPEN,
                    'openedBy' => $user->userId,
                ]);

            if (!$cashShift) {
                throw new ServiceException('No puedes realizar ventas, no tienes una caja abierta');
            }
            $data = $this->saleUtils->fillSaleValues($data);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

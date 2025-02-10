<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Application\Session\Security;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Entity\CashShift;
use App\Enum\CashShiftStatus;
use App\Sale\Domain\CreateSaleUseCase;
use App\Sale\Domain\DeleteSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Domain\Sale;
use App\Sale\Domain\UpdateSaleUseCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ApiProcessorInterface<Sale, Sale>
 */
readonly class SaleProcessor implements ApiProcessorInterface
{
    public function __construct(
        public GetOneSaleUseCase $getOneUseCase,
        public CreateSaleUseCase $createUseCase,
        public UpdateSaleUseCase $updateUseCase,
        public DeleteSaleUseCase $deleteUseCase,
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?Sale {
        $user = $this->security->connectedUser();

        if ($operation instanceof DeleteOperationInterface) {
            if ($data->movimiento_id) {
                $this->deleteUseCase->__invoke($data->movimiento_id);
            }

            return null;
        }

        if ($data->movimiento_id) {
            return $this->updateUseCase->__invoke($data);
        }

        $cashShift = $this->entityManager->getRepository(CashShift::class)
            ->findOneBy([
                'cashRegister' => $data->caja_id,
                'status' => CashShiftStatus::OPEN,
                'openedBy' => $user->userId,
            ]);

        if (!$cashShift) {
            throw new ServiceException('No puedes realizar ventas, no tienes una caja abierta');
        }

        return $this->createUseCase->__invoke($data);
    }
}

<?php

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Domain\Common\ApiResponse;
use App\Dto\CashRegister\CashRegisterCloseDto;
use App\Entity\CashShift;
use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Enum\CashShiftStatus;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CashRegisterCloseProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param CashRegisterCloseDto $data
     *
     * @throws Exception
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $this->entityManager->getConnection()->beginTransaction();
        try {
            $cashShift = $this->entityManager->find(
                CashShift::class,
                $uriVariables['id']
            );
            $cashPaymentMethodIRI = 'api/v1/payment-methods/'
                . PaymentMethod::CASH;
            $cashClosureKey = array_search(
                $cashPaymentMethodIRI,
                array_column($data->closureItems, 'paymentMethod')
            );
            $cashShift->setClosingBalance(
                $data->closureItems[$cashClosureKey]->getCountedAmount()
            );
            $cashShift->setStatus(CashShiftStatus::CLOSED);
            $cashShift->setClosedBy(
                $this->entityManager->getReference(
                    User::class,
                    $this->security->connectedUser()->userId
                )
            );
            $cashShift->setClosedAt(new \DateTimeImmutable());
            $this->entityManager->persist($cashShift);

            foreach ($data->closureItems as $cashClosure) {
                $this->entityManager->persist($cashClosure);
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (ORMException $e) {
            $this->entityManager->getConnection()->rollBack();

            return new ApiResponse(
                success: false,
                message: 'Error al cerrar la caja',
                data: $e->getMessage(),
            );
        }

        return new ApiResponse(
            success: true,
            message: 'Caja cerrada correctamente',
        );
    }
}

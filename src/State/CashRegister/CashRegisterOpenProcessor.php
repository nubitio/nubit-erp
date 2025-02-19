<?php

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Dto\CashRegister\CashRegisterOpenDto;
use App\Entity\CashMovement;
use App\Entity\CashRegister;
use App\Entity\CashShift;
use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Enum\CashMovementType;
use App\Enum\CashShiftStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CashRegisterOpenProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param CashRegisterOpenDto $data
     *
     * @throws ORMException
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CashShift {
        $user = $this->security->connectedUser();
        $cashShift = new CashShift();

        $cashShift->setCashRegister(
            $this->entityManager->getReference(
                CashRegister::class,
                $data->id
            )
        );
        $cashShift->setStatus(CashShiftStatus::OPEN);
        $cashShift->setOpeningBalance($data->openingBalance);
        $cashShift->setOpenedBy(
            $this->entityManager->getReference(
                User::class,
                $user->userId
            )
        );
        $cashShift->setOpenedAt(new \DateTimeImmutable());
        $this->entityManager->persist($cashShift);

        $cashMovement = new CashMovement();
        $cashMovement->setCashShift($cashShift);
        $cashMovement->setType(CashMovementType::INCOME);
        $cashMovement->setAmount($data->openingBalance);
        $cashMovement->setPaymentMethod(
            $this->entityManager->getReference(
                PaymentMethod::class,
                PaymentMethod::CASH
            )
        );
        $cashMovement->setDescription('Saldo inicial');
        $cashMovement->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($cashMovement);

        $this->entityManager->flush();

        return $cashShift;
    }
}

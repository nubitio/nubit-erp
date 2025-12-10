<?php

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Dto\CashRegister\CashRegisterOpenDto;
use App\Entity\CashMovement;
use App\Entity\CashMovementCategory;
use App\Entity\CashRegister;
use App\Entity\CashShift;
use App\Entity\User;
use App\Enum\CashMovementType;
use App\Enum\CashShiftStatus;
use App\Repository\CashMovementCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CashRegisterOpenProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private CashMovementCategoryRepository $cashMovementCategoryRepository,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param CashRegisterOpenDto $data
     *
     * @throws ORMException
     */
    #[\Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CashShift {
        $user = $this->security->connectedUser();
        $cashShift = new CashShift();

        $cashShift->setCashRegister($this->entityManager->getReference(CashRegister::class, $data->id));
        $cashShift->setStatus(CashShiftStatus::OPEN);
        $cashShift->setOpeningBalance($data->openingBalance);
        $cashShift->setOpenedBy($this->entityManager->getReference(User::class, $user->userId));
        $cashShift->setOpenedAt(new \DateTimeImmutable());

        $this->entityManager->persist($cashShift);

        $category = $this->cashMovementCategoryRepository->findOneBy(
            ['code' => CashMovementCategory::CASH_REPLENISHMENT]
        );
        if (!$category instanceof CashMovementCategory) {
            throw new \RuntimeException('Categoría de reposición de fondo no encontrada.');
        }

        $cashMovement = new CashMovement();
        $cashMovement->setCashShift($cashShift);
        $cashMovement->setCategory($category);
        $cashMovement->setType($category->getType() ?? CashMovementType::INCOME);
        $cashMovement->setAmount($data->openingBalance);
        $cashMovement->setDescription('Saldo inicial');
        $cashMovement->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($cashMovement);

        $this->entityManager->flush();

        return $cashShift;
    }
}

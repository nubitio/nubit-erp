<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use DateTimeImmutable;
use RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Dto\CashRegister\CashRegisterOpenDto;
use App\Entity\CashMovement;
use App\Entity\CashMovementCategory;
use App\Entity\CashRegister;
use App\Entity\CashShift;
use App\Entity\User;
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
    #[Override]
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
        $cashShift->setOpeningBalance(number_format($data->openingBalance, 2, '.', ''));
        $cashShift->setOpenedBy($this->entityManager->getReference(User::class, $user->userId));
        $cashShift->setOpenedAt(new DateTimeImmutable());

        $this->entityManager->persist($cashShift);

        $category = $this->cashMovementCategoryRepository->findOneBy(
            ['code' => CashMovementCategory::CASH_REPLENISHMENT]
        );
        if (!$category instanceof CashMovementCategory) {
            throw new RuntimeException('Categoría de reposición de fondo no encontrada.');
        }

        $cashMovement = new CashMovement();
        $cashMovement->setCashShift($cashShift);
        $cashMovement->setCategory($category);
        $cashMovement->setType($category->getType());
        $cashMovement->setAmount(number_format($data->openingBalance, 2, '.', ''));
        $cashMovement->setDescription('Saldo inicial');
        $cashMovement->setCreatedAt(new DateTimeImmutable());

        $this->entityManager->persist($cashMovement);

        $this->entityManager->flush();

        return $cashShift;
    }
}

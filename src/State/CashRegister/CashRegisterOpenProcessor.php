<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Security\Auth\BranchContextService;
use App\Dto\CashRegister\CashRegisterOpenDto;
use App\Entity\CashMovement;
use App\Entity\CashMovementCategory;
use App\Entity\CashRegister;
use App\Entity\CashShift;
use App\Entity\User;
use App\Enum\CashShiftStatus;
use App\Repository\BranchRepository;
use App\Repository\CashMovementCategoryRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<CashRegisterOpenDto, CashShift>
 */
readonly class CashRegisterOpenProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private CashMovementCategoryRepository $cashMovementCategoryRepository,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param CashRegisterOpenDto $data
     *
     * @throws ORMException
     * @throws MathException
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CashShift {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $scaledAmount = BigDecimal::of($data->openingBalance)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();

        $cashRegister = $this->entityManager->getReference(CashRegister::class, $data->id);
        if (!$cashRegister instanceof CashRegister) {
            throw new NotFoundHttpException('Caja registradora no encontrada.');
        }

        $userRef = $this->entityManager->getReference(User::class, $user->userId);
        if (!$userRef instanceof User) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        $cashShift = new CashShift();
        $cashShift->setCashRegister($cashRegister);
        $cashShift->setStatus(CashShiftStatus::OPEN);
        $cashShift->setOpeningBalance($scaledAmount);
        $cashShift->setOpenedBy($userRef);
        $cashShift->setOpenedAt(new DateTimeImmutable());

        // Set branch from current context
        $branchId = $this->branchContextService->getCurrentBranchId();
        $branch = $branchId !== null ? $this->branchRepository->find($branchId) : null;
        $cashShift->setBranch($branch);

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
        $cashMovement->setAmount($scaledAmount);
        $cashMovement->setDescription('Saldo inicial');
        $cashMovement->setCreatedAt(new DateTimeImmutable());
        $cashMovement->setBranch($branch);

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->entityManager->persist($cashShift);
            $this->entityManager->persist($cashMovement);
            $this->entityManager->flush();

            if ($branchId !== null) {
                $connection->insert('cash_sessions', [
                    'legacy_cash_shift_id' => $cashShift->getId(),
                    'cash_register_id'     => $data->id,
                    'branch_id'            => $branchId,
                    'opened_by'            => $user->userId,
                    'status'               => 'OPEN',
                    'opening_amount'       => $scaledAmount,
                    'opened_at'            => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            }

            $connection->commit();
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();

            throw new ConflictHttpException('Esta caja ya tiene un turno abierto.');
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        return $cashShift;
    }
}

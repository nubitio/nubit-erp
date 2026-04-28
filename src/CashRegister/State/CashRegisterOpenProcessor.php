<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Security\Auth\BranchContextService;
use App\CashRegister\Dto\CashRegisterOpenDto;
use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Entity\CashSession;
use App\Identity\Entity\User;
use App\Organization\Repository\BranchRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<CashRegisterOpenDto, CashSession>
 */
readonly class CashRegisterOpenProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
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
    ): CashSession {
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

        $branchId = $this->branchContextService->getCurrentBranchId();
        if ($branchId === null) {
            throw new NotFoundHttpException('Contexto de sucursal no encontrado.');
        }

        $branch = $this->branchRepository->find($branchId);
        if ($branch === null) {
            throw new NotFoundHttpException('Sucursal no encontrada.');
        }

        $cashSession = new CashSession();
        $cashSession->setCashRegister($cashRegister);
        $cashSession->setBranch($branch);
        $cashSession->setOpenedBy($userRef);
        $cashSession->setStatus('OPEN');
        $cashSession->setOpeningAmount($scaledAmount);
        $cashSession->setOpenedAt(new DateTimeImmutable());
        $cashSession->setCreatedAt(new DateTimeImmutable());

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->entityManager->persist($cashSession);
            $this->entityManager->flush();

            $connection->executeStatement(
                'INSERT INTO cash_movements (cash_session_id, movement_type, amount, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    $cashSession->getId(),
                    'OPENING',
                    $scaledAmount,
                    'Saldo inicial',
                    $user->userId,
                ]
            );

            $connection->commit();
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();

            throw new ConflictHttpException('Esta caja ya tiene un turno abierto.');
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        return $cashSession;
    }
}

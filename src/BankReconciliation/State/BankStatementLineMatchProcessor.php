<?php

declare(strict_types=1);

namespace App\BankReconciliation\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\BankReconciliation\Dto\BankStatementLineMatchDto;
use App\BankReconciliation\Entity\BankStatementLine;
use App\BankReconciliation\Service\BankReconciliationMatchService;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Service\CashMovementHydrator;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<BankStatementLineMatchDto, BankStatementLine>
 */
readonly class BankStatementLineMatchProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private BankReconciliationMatchService $matchService,
        private CashMovementHydrator $cashMovementHydrator,
    ) {}

    /**
     * @inheritdoc
     *
     * @param BankStatementLineMatchDto $data
     *
     * @throws Exception
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): BankStatementLine {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $line = $this->entityManager->find(BankStatementLine::class, $data->id);
        if (!$line instanceof BankStatementLine) {
            throw new NotFoundHttpException('Línea de extracto no encontrada.');
        }

        if ($data->sourceType === BankReconciliationMatchService::TREASURY_SOURCE_TYPE) {
            $userRef = $this->entityManager->getReference(User::class, $user->userId);
            if (!$userRef instanceof User) {
                throw new NotFoundHttpException('Usuario no encontrado.');
            }
            $this->matchService->confirmTreasuryMatch($line, (string) $data->sourceId, $userRef, 'MANUAL');

            return $line;
        }

        $sourceClass = BankReconciliationMatchService::SOURCE_TYPES[$data->sourceType] ?? null;
        if ($sourceClass === null) {
            throw new BadRequestHttpException(sprintf('sourceType debe ser uno de: %s.', implode(', ', [
                ...array_keys(BankReconciliationMatchService::SOURCE_TYPES),
                BankReconciliationMatchService::TREASURY_SOURCE_TYPE,
            ])));
        }

        // CashMovement is a DBAL-backed API model rather than a Doctrine
        // entity, so resolve it from the v2 table through its hydrator.
        if ($sourceClass === CashMovement::class) {
            $sourceId = (int) $data->sourceId;
            $source = $this->cashMovementHydrator->findById($sourceId);
        } else {
            $source = $this->entityManager->find($sourceClass, $data->sourceId);
        }

        if ($source === null) {
            throw new NotFoundHttpException('Movimiento a conciliar no encontrado.');
        }

        $userRef = $this->entityManager->getReference(User::class, $user->userId);
        if (!$userRef instanceof User) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        $this->matchService->confirmMatch($line, $source, $userRef, 'MANUAL');

        return $line;
    }
}

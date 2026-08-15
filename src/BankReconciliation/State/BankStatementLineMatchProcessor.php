<?php

declare(strict_types=1);

namespace App\BankReconciliation\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\BankReconciliation\Dto\BankStatementLineMatchDto;
use App\BankReconciliation\Entity\BankStatementLine;
use App\BankReconciliation\Service\BankReconciliationMatchService;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
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
    ) {}

    /**
     * @inheritdoc
     *
     * @param BankStatementLineMatchDto $data
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

        $sourceClass = BankReconciliationMatchService::SOURCE_TYPES[$data->sourceType] ?? null;
        if ($sourceClass === null) {
            throw new BadRequestHttpException(sprintf('sourceType debe ser uno de: %s.', implode(
                ', ',
                array_keys(BankReconciliationMatchService::SOURCE_TYPES),
            )));
        }

        $source = $this->entityManager->find($sourceClass, $data->sourceId);
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

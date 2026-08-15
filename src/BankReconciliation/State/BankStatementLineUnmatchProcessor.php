<?php

declare(strict_types=1);

namespace App\BankReconciliation\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\BankReconciliation\Dto\BankStatementLineUnmatchDto;
use App\BankReconciliation\Entity\BankStatementLine;
use App\BankReconciliation\Service\BankReconciliationMatchService;
use App\Identity\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<BankStatementLineUnmatchDto, BankStatementLine>
 */
readonly class BankStatementLineUnmatchProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private BankReconciliationMatchService $matchService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param BankStatementLineUnmatchDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): BankStatementLine {
        if ($this->security->connectedUser() === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $line = $this->entityManager->find(BankStatementLine::class, $data->id);
        if (!$line instanceof BankStatementLine) {
            throw new NotFoundHttpException('Línea de extracto no encontrada.');
        }

        $this->matchService->undoMatch($line);

        return $line;
    }
}

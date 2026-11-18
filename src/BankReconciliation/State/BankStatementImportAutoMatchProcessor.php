<?php

declare(strict_types=1);

namespace App\BankReconciliation\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\BankReconciliation\Dto\BankStatementImportAutoMatchDto;
use App\BankReconciliation\Entity\BankStatementImport;
use App\BankReconciliation\Repository\BankStatementLineRepository;
use App\BankReconciliation\Service\BankReconciliationMatchingService;
use App\BankReconciliation\Service\BankReconciliationMatchService;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Service\CashMovementHydrator;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Iterates the UNMATCHED lines of an import batch and auto-confirms the
 * ones whose top suggestion is EXACT confidence. Lines with only
 * APPROXIMATE or no suggestions are left for the manual two-pane screen.
 * Kept as an explicit, user-triggered, re-runnable action rather than
 * happening silently inside the parser.
 *
 * @implements ProcessorInterface<BankStatementImportAutoMatchDto, BankStatementImport>
 */
readonly class BankStatementImportAutoMatchProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private BankStatementLineRepository $lineRepository,
        private BankReconciliationMatchingService $matchingService,
        private BankReconciliationMatchService $matchService,
        private CashMovementHydrator $cashMovementHydrator,
    ) {}

    /**
     * @inheritdoc
     *
     * @param BankStatementImportAutoMatchDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): BankStatementImport {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $import = $this->entityManager->find(BankStatementImport::class, $data->id);
        if (!$import instanceof BankStatementImport) {
            throw new NotFoundHttpException('Import no encontrado.');
        }

        $userRef = $this->entityManager->getReference(User::class, $user->userId);
        if (!$userRef instanceof User) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        foreach ($this->lineRepository->findUnmatchedByImportBatch($import) as $line) {
            $suggestions = $this->matchingService->suggestionsFor($line);
            $top = $suggestions[0] ?? null;
            if ($top === null || $top->confidence !== 'EXACT') {
                continue;
            }

            if ($top->sourceType === BankReconciliationMatchService::TREASURY_SOURCE_TYPE) {
                $this->matchService->confirmTreasuryMatch($line, (string) $top->sourceId, $userRef, 'AUTO');
                continue;
            }

            $sourceClass = BankReconciliationMatchService::SOURCE_TYPES[$top->sourceType] ?? null;
            if ($sourceClass === null) {
                continue;
            }

            $source = $sourceClass === CashMovement::class
                ? $this->cashMovementHydrator->findById((int) $top->sourceId)
                : $this->entityManager->find($sourceClass, $top->sourceId);
            if ($source === null) {
                continue;
            }

            $this->matchService->confirmMatch($line, $source, $userRef, 'AUTO');
        }

        return $import;
    }
}

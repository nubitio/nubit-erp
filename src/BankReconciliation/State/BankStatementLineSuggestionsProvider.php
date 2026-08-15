<?php

declare(strict_types=1);

namespace App\BankReconciliation\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\BankReconciliation\Dto\BankStatementLineSuggestionDto;
use App\BankReconciliation\Entity\BankStatementLine;
use App\BankReconciliation\Service\BankReconciliationMatchingService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<BankStatementLineSuggestionDto>
 */
readonly class BankStatementLineSuggestionsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BankReconciliationMatchingService $matchingService,
    ) {}

    /**
     * @inheritdoc
     *
     * @return list<BankStatementLineSuggestionDto>
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $line = $this->entityManager->find(BankStatementLine::class, $uriVariables['id'] ?? null);
        if (!$line instanceof BankStatementLine) {
            throw new NotFoundHttpException('Línea de extracto no encontrada.');
        }

        return $this->matchingService->suggestionsFor($line);
    }
}

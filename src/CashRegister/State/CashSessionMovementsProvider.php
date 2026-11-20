<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Service\CashMovementHydrator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<CashMovement>
 */
readonly class CashSessionMovementsProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
        private CashMovementHydrator $hydrator,
    ) {}

    /**
     * @inheritdoc
     * @throws Exception
     * @return list<CashMovement>
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $cashSessionId = $uriVariables['id'];

        $session = $this->connection->fetchAssociative('SELECT id FROM cash_sessions WHERE id = ?', [$cashSessionId]);

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %s.', $cashSessionId));
        }

        $sessionId = (int) $session['id'];

        $rows = $this->connection->fetchAllAssociative('SELECT id, cash_session_id, category_id, amount, description, created_at, movement_type
             FROM cash_movements
             WHERE cash_session_id = ?
             ORDER BY created_at ASC', [$sessionId]);

        return array_map($this->hydrator->fromRow(...), $rows);
    }
}

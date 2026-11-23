<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashPosStateDto;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Security\Security;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Sales are tied to the session the *user* opened (see SaleProcessor), not to "some register of the
 * branch is open", so the POS reads its state from here instead of from the branch dashboard.
 *
 * @implements ProviderInterface<CashPosStateDto>
 */
readonly class CashPosStateProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
        private Security $security,
        private BranchContextServiceInterface $branchContext,
    ) {}

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CashPosStateDto
    {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $row = $this->connection->fetchAssociative("SELECT cs.id, cs.cash_register_id, cr.name, cs.opened_at, cs.opening_amount
             FROM cash_sessions cs
             JOIN cash_register cr ON cr.id = cs.cash_register_id
             WHERE cs.opened_by = ? AND cs.status = 'OPEN'
             ORDER BY cs.opened_at DESC, cs.id DESC
             LIMIT 1", [$user->userId]);

        $session = $row === false
            ? null
            : [
                'id' => (int) $row['id'],
                'cashRegisterId' => (int) $row['cash_register_id'],
                'cashRegisterName' => (string) $row['name'],
                'openedAt' => new DateTimeImmutable((string) $row['opened_at'])->format(DATE_ATOM),
                'openingAmount' => BigDecimal::of((string) $row['opening_amount'])->toScale(
                    2,
                    RoundingMode::HALF_UP,
                )->__toString(),
            ];

        $branchId = $this->branchContext->getCurrentBranchId();
        $available = [];
        $busy = [];
        if ($session === null && $branchId !== null) {
            $rows = $this->connection->fetchAllAssociative("SELECT c.id, c.name FROM cash_register c
                 WHERE c.branch_id = ?
                   AND NOT EXISTS (SELECT 1 FROM cash_sessions cs WHERE cs.cash_register_id = c.id AND cs.status = 'OPEN')
                 ORDER BY c.name, c.id", [$branchId]);
            $available = array_map(static fn(array $r): array => [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
            ], $rows);

            $busyRows = $this->connection->fetchAllAssociative("SELECT c.id, c.name, u.username, cs.opened_at
                 FROM cash_register c
                 JOIN cash_sessions cs ON cs.cash_register_id = c.id AND cs.status = 'OPEN'
                 LEFT JOIN usuario u ON u.usuario_id = cs.opened_by
                 WHERE c.branch_id = ?
                 ORDER BY c.name, c.id", [$branchId]);
            $busy = array_map(static fn(array $r): array => [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'cashier' => $r['username'] === null ? null : (string) $r['username'],
                'openedAt' => new DateTimeImmutable((string) $r['opened_at'])->format(DATE_ATOM),
            ], $busyRows);
        }

        return new CashPosStateDto($session, $available, $busy);
    }
}

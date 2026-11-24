<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashSessionRecentDto;
use App\Identity\Auth\BranchContextServiceInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;

/**
 * @implements ProviderInterface<CashSessionRecentDto>
 */
readonly class CashSessionRecentProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 10;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private Connection $connection,
        private BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @return list<CashSessionRecentDto>
     * @throws Exception|DateMalformedStringException
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $branchId = $this->branchContext->getCurrentBranchId();

        if ($branchId === null) {
            return [];
        }

        $requested = (int) ($context['filters']['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $requested));
        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $registerId = (int) ($context['filters']['cashRegisterId'] ?? 0);
        $registerFilter = $registerId > 0 ? 'AND cs.cash_register_id = ?' : '';
        $params = $registerId > 0 ? [$branchId, $registerId] : [$branchId];

        $rows = $this->connection->fetchAllAssociative(
            "SELECT cs.id, cs.cash_register_id, cr.name AS register_name, u.username AS cashier,
                    cs.opened_at, cs.closed_at, cs.status, cs.opening_amount, cs.difference_amount,
                    COALESCE((SELECT SUM(s.grand_total) FROM sales s
                              WHERE s.cash_session_id = cs.id AND s.deleted_at IS NULL AND s.status <> 'VOIDED'), 0) AS sales
             FROM cash_sessions cs
             JOIN cash_register cr ON cr.id = cs.cash_register_id
             LEFT JOIN usuario u ON u.usuario_id = cs.opened_by
             WHERE cs.branch_id = ? {$registerFilter}
             ORDER BY cs.opened_at DESC, cs.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        return array_map(
            fn(array $row): CashSessionRecentDto => new CashSessionRecentDto(
                id: (int) $row['id'],
                cashRegisterId: (int) $row['cash_register_id'],
                cashRegisterName: (string) $row['register_name'],
                cashier: $row['cashier'] === null ? null : (string) $row['cashier'],
                openedAt: new DateTimeImmutable((string) $row['opened_at']),
                closedAt: $row['closed_at'] === null ? null : new DateTimeImmutable((string) $row['closed_at']),
                status: $row['status'] === 'OPEN' ? 'open' : 'closed',
                openingAmount: $this->money($row['opening_amount']),
                sales: $this->money($row['sales']),
                difference: $row['difference_amount'] === null ? null : $this->money($row['difference_amount']),
            ),
            $rows,
        );
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}

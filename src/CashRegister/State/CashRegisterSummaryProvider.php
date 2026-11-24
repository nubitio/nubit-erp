<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashRegisterSummaryDto;
use App\Identity\Auth\BranchContextServiceInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;

/**
 * @implements ProviderInterface<CashRegisterSummaryDto>
 */
readonly class CashRegisterSummaryProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
        private BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CashRegisterSummaryDto
    {
        $branchId = $this->branchContext->getCurrentBranchId();

        if ($branchId === null) {
            return new CashRegisterSummaryDto('0.00', 0, '0.00', 0);
        }

        $sales = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(s.grand_total), 0)
             FROM sales s
             JOIN cash_sessions cs ON cs.id = s.cash_session_id
             WHERE cs.branch_id = ? AND DATE(s.issued_at) = CURDATE()
               AND s.deleted_at IS NULL AND s.status <> 'VOIDED'",
            [$branchId],
        );

        $registers = $this->connection->fetchOne(
            "SELECT COUNT(DISTINCT cash_register_id) FROM cash_sessions
             WHERE branch_id = ? AND (status = 'OPEN' OR DATE(opened_at) = CURDATE())",
            [$branchId],
        );

        $closures = $this->connection->fetchAssociative(
            "SELECT COALESCE(SUM(difference_amount), 0) AS net,
                    COALESCE(SUM(CASE WHEN difference_amount < 0 THEN 1 ELSE 0 END), 0) AS shortages
             FROM cash_sessions
             WHERE branch_id = ? AND status = 'CLOSED' AND closed_at >= (NOW() - INTERVAL 7 DAY)",
            [$branchId],
        );

        $shiftsTotal = $this->connection->fetchOne('SELECT COUNT(*) FROM cash_sessions WHERE branch_id = ?', [
            $branchId,
        ]);

        return new CashRegisterSummaryDto(
            salesToday: $this->money($sales),
            registersWithShiftToday: (int) $registers,
            closureDifference7Days: $this->money($closures['net'] ?? 0),
            shortageShifts7Days: (int) ($closures['shortages'] ?? 0),
            shiftsTotal: (int) $shiftsTotal,
        );
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}

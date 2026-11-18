<?php

declare(strict_types=1);

namespace App\Payroll\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Payroll\Entity\PayrollRun;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\RecordsMoneyMovement;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Records where the money of a payroll run left from: the user's open drawer when paid in cash, otherwise the
 * chosen or mapped bank or wallet account. One movement for the whole net amount of the run. A no-op when
 * Treasury is off for the tenant.
 *
 * Called before the run is saved as paid, so a Treasury refusal (for instance cash with no open drawer) leaves
 * the run unpaid.
 */
final readonly class PayrollTreasury
{
    public function __construct(
        private RecordsMoneyMovement $treasury,
        private BranchContextServiceInterface $branchContext,
    ) {}

    public function record(
        PayrollRun $run,
        string $netAmount,
        ?int $paymentMethodId,
        ?int $userId,
        ?string $moneyAccountId,
    ): void {
        if (Money::money($netAmount)->isLessThanOrEqualTo(0)) {
            return;
        }

        try {
            $this->treasury->record(new MoneyMovementRequest(
                key: new MovementKey(MovementSource::PayrollPayment, (string) $run->getId()),
                direction: Direction::Out,
                amount: $netAmount,
                currency: 'PEN',
                branchId: $this->branchContext->getCurrentBranchId() ?? '',
                occurredAt: new DateTimeImmutable(),
                isCash: $paymentMethodId === PaymentMethod::CASH,
                paymentMethodId: $paymentMethodId,
                userId: $userId,
                description: sprintf('Pago de planilla %s', $run->getPeriod()->format('Y-m')),
                accountId: $moneyAccountId,
            ));
        } catch (TreasuryException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}

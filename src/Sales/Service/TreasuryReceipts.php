<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\Party;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\RecordsMoneyMovement;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Puts money a customer paid into Treasury. Cash is not handled here: it already lands in the collector's open
 * drawer through the sales write gateway. Everything else goes to the chosen account, the payment method's
 * mapping or the suspense account. A no-op when Treasury is off for the tenant.
 *
 * Callers invoke it inside the transaction that persists the payment, so a Treasury refusal rolls the payment
 * back instead of leaving a payment with no movement.
 */
final readonly class TreasuryReceipts
{
    public function __construct(
        private RecordsMoneyMovement $treasury,
        private BranchContextServiceInterface $branchContext,
    ) {}

    public function record(TreasuryReceipt $receipt): void
    {
        if ($receipt->paymentMethod->getId() === PaymentMethod::CASH) {
            return;
        }

        try {
            $this->treasury->record(new MoneyMovementRequest(
                key: new MovementKey($receipt->source, $receipt->sourceId),
                direction: Direction::In,
                amount: $receipt->amount,
                currency: $receipt->currency,
                branchId: $receipt->branchId ?? $this->branchContext->getCurrentBranchId() ?? '',
                occurredAt: $receipt->paidAt,
                paymentMethodId: $receipt->paymentMethod->getId(),
                userId: $receipt->userId,
                party: $receipt->customerId === null ? null : new Party('customer', $receipt->customerId),
                reference: $receipt->reference,
                description: $receipt->description,
                accountId: $receipt->moneyAccountId,
            ));
        } catch (TreasuryException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}

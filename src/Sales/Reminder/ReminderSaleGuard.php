<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use App\Identity\Auth\BranchContextServiceInterface;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\SaleRecordRepository;

/** Loads a sale for a reminder, enforcing the current branch and an open balance. */
final readonly class ReminderSaleGuard
{
    public function __construct(
        private SaleRecordRepository $sales,
        private BranchContextServiceInterface $branchContext,
    ) {}

    /** The sale if it exists in the current branch; its history may be read even when paid. */
    public function inBranch(string $saleId): SaleRecord
    {
        $sale = $this->sales->find($saleId);
        $branchId = $this->branchContext->getCurrentBranchId();
        if (!$sale instanceof SaleRecord || $branchId === null || $sale->getBranch()?->getId() !== $branchId) {
            throw ReminderException::saleNotFound();
        }

        return $sale;
    }

    /** The sale if, besides being in the branch, it still has something to collect. */
    public function collectable(string $saleId): SaleRecord
    {
        $sale = $this->inBranch($saleId);
        $balance = $sale->getBalanceDue();
        if ($sale->getStatus() === 'VOIDED' || !is_numeric($balance) || (float) $balance <= 0) {
            throw ReminderException::nothingToCollect();
        }

        return $sale;
    }
}

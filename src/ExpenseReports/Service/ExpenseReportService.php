<?php

declare(strict_types=1);

namespace App\ExpenseReports\Service;

use App\ExpenseReports\Entity\ExpenseReport;
use Nubit\Platform\Exception\ValidationException;

/**
 * Guards the ExpenseReport status transitions. Reimbursement is a status
 * flag only (reimbursedAt + REIMBURSED) — it does not create a
 * CashMovement/SupplierPayment; see the Fase 5 plan notes for why that's
 * intentionally out of scope here.
 */
readonly class ExpenseReportService
{
    /** Forward-only transition graph; a status can only move to the states listed. */
    private const array ALLOWED_TRANSITIONS = [
        ExpenseReport::PENDING => [ExpenseReport::APPROVED, ExpenseReport::REJECTED],
        ExpenseReport::APPROVED => [ExpenseReport::REIMBURSED],
        ExpenseReport::REJECTED => [],
        ExpenseReport::REIMBURSED => [],
    ];

    public function transitionTo(ExpenseReport $expenseReport, string $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$expenseReport->getStatus()] ?? [];
        if (!in_array($newStatus, $allowed, strict: true)) {
            throw ValidationException::withErrors([
                'status' => [sprintf(
                    'No se puede pasar el gasto de "%s" a "%s".',
                    $expenseReport->getStatus(),
                    $newStatus,
                )],
            ]);
        }

        $expenseReport->setStatus($newStatus);
    }
}

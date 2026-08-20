<?php

declare(strict_types=1);

namespace App\Payroll\Service;

use App\Payroll\Entity\PayrollRun;
use Nubit\Platform\Exception\ValidationException;

/**
 * Guards PayrollRun status transitions.
 */
readonly class PayrollRunService
{
    /** Forward-only transition graph; a status can only move to the states listed. */
    private const array ALLOWED_TRANSITIONS = [
        PayrollRun::DRAFT => [PayrollRun::CALCULATED],
        PayrollRun::CALCULATED => [PayrollRun::CALCULATED, PayrollRun::APPROVED],
        PayrollRun::APPROVED => [PayrollRun::PAID],
        PayrollRun::PAID => [],
    ];

    public function transitionTo(PayrollRun $payrollRun, string $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$payrollRun->getStatus()] ?? [];
        if (!in_array($newStatus, $allowed, strict: true)) {
            throw ValidationException::withErrors([
                'status' => [sprintf(
                    'No se puede pasar la corrida de planilla de "%s" a "%s".',
                    $payrollRun->getStatus(),
                    $newStatus,
                )],
            ]);
        }

        $payrollRun->setStatus($newStatus);
    }
}

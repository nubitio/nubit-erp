<?php

declare(strict_types=1);

namespace App\Tests\ExpenseReports\Service;

use App\ExpenseReports\Entity\ExpenseReport;
use App\ExpenseReports\Service\ExpenseReportService;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ExpenseReportServiceTest extends TestCase
{
    public function testTransitionToAllowsPendingToApproved(): void
    {
        $report = new ExpenseReport();
        static::assertSame(ExpenseReport::PENDING, $report->getStatus());

        new ExpenseReportService()->transitionTo($report, ExpenseReport::APPROVED);

        static::assertSame(ExpenseReport::APPROVED, $report->getStatus());
    }

    public function testTransitionToAllowsPendingToRejected(): void
    {
        $report = new ExpenseReport();

        new ExpenseReportService()->transitionTo($report, ExpenseReport::REJECTED);

        static::assertSame(ExpenseReport::REJECTED, $report->getStatus());
    }

    public function testTransitionToAllowsApprovedToReimbursed(): void
    {
        $report = new ExpenseReport()->setStatus(ExpenseReport::APPROVED);

        new ExpenseReportService()->transitionTo($report, ExpenseReport::REIMBURSED);

        static::assertSame(ExpenseReport::REIMBURSED, $report->getStatus());
    }

    public function testTransitionToRejectsPendingDirectlyToReimbursed(): void
    {
        $report = new ExpenseReport();

        $this->expectException(ValidationException::class);
        new ExpenseReportService()->transitionTo($report, ExpenseReport::REIMBURSED);
    }

    public function testTransitionToRejectsTransitionFromTerminalRejected(): void
    {
        $report = new ExpenseReport()->setStatus(ExpenseReport::REJECTED);

        $this->expectException(ValidationException::class);
        new ExpenseReportService()->transitionTo($report, ExpenseReport::APPROVED);
    }

    public function testTransitionToRejectsTransitionFromTerminalReimbursed(): void
    {
        $report = new ExpenseReport()->setStatus(ExpenseReport::REIMBURSED);

        $this->expectException(ValidationException::class);
        new ExpenseReportService()->transitionTo($report, ExpenseReport::APPROVED);
    }
}

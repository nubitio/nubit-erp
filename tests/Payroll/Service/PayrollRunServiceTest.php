<?php

declare(strict_types=1);

namespace App\Tests\Payroll\Service;

use App\Payroll\Entity\PayrollRun;
use App\Payroll\Service\PayrollRunService;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class PayrollRunServiceTest extends TestCase
{
    public function testTransitionToAllowsDraftToCalculated(): void
    {
        $run = new PayrollRun();
        static::assertSame(PayrollRun::DRAFT, $run->getStatus());

        new PayrollRunService()->transitionTo($run, PayrollRun::CALCULATED);

        static::assertSame(PayrollRun::CALCULATED, $run->getStatus());
    }

    public function testTransitionToAllowsRecalculatingWhileCalculated(): void
    {
        $run = new PayrollRun()->setStatus(PayrollRun::CALCULATED);

        new PayrollRunService()->transitionTo($run, PayrollRun::CALCULATED);

        static::assertSame(PayrollRun::CALCULATED, $run->getStatus());
    }

    public function testTransitionToAllowsCalculatedToApproved(): void
    {
        $run = new PayrollRun()->setStatus(PayrollRun::CALCULATED);

        new PayrollRunService()->transitionTo($run, PayrollRun::APPROVED);

        static::assertSame(PayrollRun::APPROVED, $run->getStatus());
    }

    public function testTransitionToAllowsApprovedToPaid(): void
    {
        $run = new PayrollRun()->setStatus(PayrollRun::APPROVED);

        new PayrollRunService()->transitionTo($run, PayrollRun::PAID);

        static::assertSame(PayrollRun::PAID, $run->getStatus());
    }

    public function testTransitionToRejectsDraftDirectlyToApproved(): void
    {
        $run = new PayrollRun();

        $this->expectException(ValidationException::class);
        new PayrollRunService()->transitionTo($run, PayrollRun::APPROVED);
    }

    public function testTransitionToRejectsTransitionFromTerminalPaid(): void
    {
        $run = new PayrollRun()->setStatus(PayrollRun::PAID);

        $this->expectException(ValidationException::class);
        new PayrollRunService()->transitionTo($run, PayrollRun::APPROVED);
    }
}

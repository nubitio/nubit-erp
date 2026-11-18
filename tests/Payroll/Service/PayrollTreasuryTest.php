<?php

declare(strict_types=1);

namespace App\Tests\Payroll\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Service\PayrollTreasury;
use App\Tests\Treasury\TreasurySpy;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Paying a payroll run records one movement for the whole net amount, out of cash or a bank or wallet. */
final class PayrollTreasuryTest extends TestCase
{
    public function testTheNetAmountLeavesOnceForTheWholeRun(): void
    {
        $spy = new TreasurySpy();

        $this->payroll($spy)->record($this->payrollRun(), '12500.40', 5, 9, 'account-1');

        self::assertCount(1, $spy->recorded);
        $request = $spy->recorded[0];
        self::assertSame(MovementSource::PayrollPayment, $request->key->source);
        self::assertSame('run-1', $request->key->id);
        self::assertSame(Direction::Out, $request->direction);
        self::assertSame('12500.40', $request->amount);
        self::assertSame('PEN', $request->currency);
        self::assertSame('ctx-branch', $request->branchId);
        self::assertFalse($request->isCash);
        self::assertSame('account-1', $request->accountId);
        self::assertSame('Pago de planilla 2026-10', $request->description);
    }

    public function testPayingInCashIsMarkedSoItLeavesTheOpenDrawer(): void
    {
        $spy = new TreasurySpy();

        $this->payroll($spy)->record($this->payrollRun(), '800.00', PaymentMethod::CASH, 9, null);

        self::assertTrue($spy->recorded[0]->isCash);
    }

    public function testARunWithNothingToPayRecordsNothing(): void
    {
        $spy = new TreasurySpy();

        $this->payroll($spy)->record($this->payrollRun(), '0.00', 5, 9, null);

        self::assertSame([], $spy->recorded);
    }

    public function testATreasuryRefusalBecomesA422SoTheRunStaysUnpaid(): void
    {
        $spy = new TreasurySpy();
        $spy->failWith = new TreasuryException(
            'No tienes una caja abierta; abre una sesión de caja para registrar efectivo.',
        );

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('caja abierta');

        $this->payroll($spy)->record($this->payrollRun(), '800.00', PaymentMethod::CASH, 9, null);
    }

    private function payroll(TreasurySpy $spy): PayrollTreasury
    {
        $context = new class implements BranchContextServiceInterface {
            public function getCurrentBranchId(): string
            {
                return 'ctx-branch';
            }

            public function hasBranch(): bool
            {
                return true;
            }
        };

        return new PayrollTreasury($spy, $context);
    }

    private function payrollRun(): PayrollRun
    {
        $run = new PayrollRun();
        new \ReflectionProperty(PayrollRun::class, 'id')->setValue($run, 'run-1');
        new \ReflectionProperty(PayrollRun::class, 'period')->setValue($run, new DateTimeImmutable('2026-10-01'));

        return $run;
    }
}

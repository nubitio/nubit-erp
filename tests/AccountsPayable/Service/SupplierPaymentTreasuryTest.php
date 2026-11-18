<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Service;

use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\Entity\SupplierPayment;
use App\AccountsPayable\Service\SupplierPaymentService;
use App\Catalog\Entity\PaymentMethod;
use App\Contacts\Entity\Supplier;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\RecordsMoneyMovement;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** What a supplier payment asks of Treasury: the movement on the way out, its refusals and its reversal. */
#[AllowMockObjectsWithoutExpectations]
final class SupplierPaymentTreasuryTest extends TestCase
{
    public function testRegisterPaymentRecordsTheMoneyLeavingInTreasury(): void
    {
        $spy = new TreasurySpy();
        $bill = $this->billWithBranchAndSupplier('USD');

        $payment = $this->treasuryService($spy)->registerPayment(
            $bill,
            $this->method(6),
            '40.00',
            new DateTimeImmutable('2026-11-18'),
            'OP-1',
            $this->user(9),
            'account-1',
        );

        static::assertCount(1, $spy->recorded);
        $request = $spy->recorded[0];
        static::assertSame(MovementSource::SupplierPayment, $request->key->source);
        static::assertSame((string) $payment->getId(), $request->key->id);
        static::assertSame(Direction::Out, $request->direction);
        static::assertSame('40.00', $request->amount);
        static::assertSame('USD', $request->currency);
        static::assertSame('branch-1', $request->branchId);
        static::assertFalse($request->isCash);
        static::assertSame(6, $request->paymentMethodId);
        static::assertSame(9, $request->userId);
        static::assertSame('supplier-1', $request->party?->id);
        static::assertSame('account-1', $request->accountId);
        static::assertSame('OP-1', $request->reference);
    }

    public function testACashPaymentIsMarkedAsCashSoItLeavesTheDrawer(): void
    {
        $spy = new TreasurySpy();

        $this->treasuryService($spy)->registerPayment(
            $this->billWithBranchAndSupplier('PEN'),
            $this->method(PaymentMethod::CASH),
            '10.00',
            new DateTimeImmutable(),
            null,
            $this->user(9),
        );

        static::assertTrue($spy->recorded[0]->isCash);
        static::assertNull($spy->recorded[0]->accountId);
    }

    public function testATreasuryRuleBecomesA422AndTheBillIsNotLeftHalfPaid(): void
    {
        $spy = new TreasurySpy();
        $spy->failWith = new TreasuryException(
            'No tienes una caja abierta; abre una sesión de caja para registrar efectivo.',
        );

        try {
            $this->treasuryService($spy)->registerPayment(
                $this->billWithBranchAndSupplier('PEN'),
                $this->method(PaymentMethod::CASH),
                '10.00',
                new DateTimeImmutable(),
                null,
                $this->user(9),
            );
            static::fail('The payment must be refused.');
        } catch (UnprocessableEntityHttpException $e) {
            static::assertStringContainsString('caja abierta', $e->getMessage());
        }
    }

    public function testReversingAPaymentReversesItsTreasuryMovement(): void
    {
        $spy = new TreasurySpy();
        $bill = $this->billWithBranchAndSupplier('PEN');
        $bill->setPaidTotal('40.00')->setBalanceDue('60.00')->setStatus(SupplierBill::PARTIAL);
        $payment = $this->paymentOn($bill, '40.00');
        new \ReflectionProperty(SupplierPayment::class, 'id')->setValue($payment, 77);

        $this->treasuryService($spy)->reversePayment($bill, $payment, 9);

        static::assertCount(1, $spy->reversed);
        [$original, $reversal, $userId] = $spy->reversed[0];
        static::assertEquals(new MovementKey(MovementSource::SupplierPayment, '77'), $original);
        static::assertEquals(new MovementKey(MovementSource::SupplierPaymentReversal, '77'), $reversal);
        static::assertSame(9, $userId);
    }

    private function treasuryService(RecordsMoneyMovement $treasury): SupplierPaymentService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn(callable $callback): mixed => $callback());
        $em->method('persist')->willReturnCallback(static function (object $entity): void {
            if ($entity instanceof SupplierPayment) {
                new \ReflectionProperty(SupplierPayment::class, 'id')->setValue($entity, 77);
            }
        });

        return new SupplierPaymentService($em, null, $treasury);
    }

    private function billWithBranchAndSupplier(string $currency): SupplierBill
    {
        $branch = new Branch();
        $this->withId($branch, 'branch-1');
        $supplier = new Supplier();
        $this->withId($supplier, 'supplier-1');

        return $this->pendingBill()->setCurrency($currency)->setBranch($branch)->setSupplier($supplier);
    }

    private function withId(object $entity, int|string $id): void
    {
        new \ReflectionProperty($entity, 'id')->setValue($entity, $id);
    }

    private function method(int $id): PaymentMethod
    {
        $method = new PaymentMethod();
        $this->withId($method, $id);

        return $method;
    }

    private function user(int $id): User
    {
        $user = new User();
        $this->withId($user, $id);

        return $user;
    }

    private function pendingBill(): SupplierBill
    {
        $bill = new SupplierBill()
            ->setAmount('100.00')
            ->setPaidTotal('0.00')
            ->setBalanceDue('100.00');
        $this->withId($bill, 'bill-1');

        return $bill;
    }

    private function paymentOn(SupplierBill $bill, string $amount): SupplierPayment
    {
        return new SupplierPayment()
            ->setSupplierBill($bill)
            ->setPaymentMethod(new PaymentMethod())
            ->setAmount($amount)
            ->setPaidAt(new DateTimeImmutable());
    }
}

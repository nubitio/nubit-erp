<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Identity\Entity\User;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SalePaymentService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SalePaymentServiceTest extends TestCase
{
    public function testReceivableAccountSurvivesFullPayment(): void
    {
        $sale = new SaleRecord();
        self::assertFalse($sale->isReceivableAccount());
        $sale->setBalanceDue('90.00');
        self::assertTrue($sale->isReceivableAccount());
        $sale->setBalanceDue('0.00');
        self::assertTrue($sale->isReceivableAccount());
    }

    /** @return iterable<string, array{SettlementBehavior, bool}> */
    public static function invalidMethods(): iterable
    {
        yield 'credit' => [SettlementBehavior::RECEIVABLE, true];
        yield 'inactive cash' => [SettlementBehavior::COLLECTED, false];
    }

    #[DataProvider('invalidMethods')]
    public function testRejectsInvalidMethodWithoutChangingDebt(SettlementBehavior $behavior, bool $active): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $sale = new SaleRecord()
            ->setPaidTotal('10.00')
            ->setBalanceDue('90.00');
        $method = new PaymentMethod()
            ->setSettlementBehavior($behavior)
            ->setIsActive($active);

        try {
            new SalePaymentService($em)->registerPayment(
                $sale,
                $method,
                '20.00',
                new DateTimeImmutable(),
                null,
                new User(),
            );
            self::fail('Invalid payment method was accepted.');
        } catch (UnprocessableEntityHttpException) {
            self::assertSame('10.00', $sale->getPaidTotal());
            self::assertSame('90.00', $sale->getBalanceDue());
            self::assertCount(0, $sale->getSalePayments());
        }
    }
}

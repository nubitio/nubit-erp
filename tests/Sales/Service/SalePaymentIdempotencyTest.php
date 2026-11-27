<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SalePaymentIdempotency;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SalePaymentIdempotencyTest extends TestCase
{
    public function testNoKeyMeansNoReplay(): void
    {
        self::assertNull(SalePaymentIdempotency::existing(
            $this->createMock(EntityManagerInterface::class),
            null,
            $this->sale('s1'),
            BigDecimal::of('40.00'),
        ));
    }

    public function testAnExistingPaymentForTheSameSaleAndAmountIsTheReplay(): void
    {
        $payment = $this->payment('s1', '40.00');

        self::assertSame($payment, SalePaymentIdempotency::existing(
            $this->em($payment),
            'k',
            $this->sale('s1'),
            BigDecimal::of('40.00'),
        ));
    }

    public function testTheKeyOfAnotherSaleOrAmountIsAConflict(): void
    {
        $this->expectException(ConflictHttpException::class);

        SalePaymentIdempotency::existing($this->em($this->payment('s2', '40.00')), 'k', $this->sale('s1'), BigDecimal::of('40.00'));
    }

    private function em(SalePayment $found): EntityManagerInterface
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($found);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    private function sale(string $id): SaleRecord
    {
        $sale = new SaleRecord();
        new \ReflectionProperty(SaleRecord::class, 'id')->setValue($sale, $id);

        return $sale;
    }

    private function payment(string $saleId, string $amount): SalePayment
    {
        return new SalePayment()->setSaleRecord($this->sale($saleId))->setAmount($amount);
    }
}

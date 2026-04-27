<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Core\Exception\ServiceException;
use App\Catalog\Entity\PaymentMethod;
use App\Entity\SaleRecord;
use App\Service\SalePaymentPlanService;
use PHPUnit\Framework\TestCase;

final class SalePaymentPlanServiceTest extends TestCase
{
    private SalePaymentPlanService $service;

    protected function setUp(): void
    {
        $this->service = new SalePaymentPlanService();
    }

    public function testFallbackSinglePaymentFromTipoPago(): void
    {
        $sale = new SaleRecord();
        $sale->setTipoPago($this->paymentMethod(2));
        $sale->setPayments([]);

        $plan = $this->service->resolveForWrite($sale, 'PEN', '120.00');

        $this->assertCount(1, $plan);
        $this->assertSame(2, $plan[0]['payment_method_id']);
        $this->assertSame('120.00', $plan[0]['amount']);
    }

    public function testResolvesMixedPayments(): void
    {
        $sale = new SaleRecord();
        $sale->setPayments([
            [
                'payment_method_id' => 1,
                'amount' => '20.00',
                'tendered_amount' => '50.00',
                'change_amount' => '30.00',
            ],
            [
                'payment_method_id' => 2,
                'amount' => '80.00',
            ],
        ]);

        $plan = $this->service->resolveForWrite($sale, 'PEN', '100.00');

        $this->assertCount(2, $plan);
        $this->assertSame(1, $plan[0]['payment_method_id']);
        $this->assertSame('20.00', $plan[0]['amount']);
        $this->assertSame('50.00', $plan[0]['tendered_amount']);
        $this->assertSame('30.00', $plan[0]['change_amount']);
        $this->assertSame(2, $plan[1]['payment_method_id']);
        $this->assertSame('80.00', $plan[1]['amount']);
    }

    public function testThrowsWhenPaymentsDoNotMatchTotal(): void
    {
        $sale = new SaleRecord();
        $sale->setPayments([
            ['payment_method_id' => 1, 'amount' => '10.00'],
            ['payment_method_id' => 2, 'amount' => '10.00'],
        ]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('no coincide con el total');

        $this->service->resolveForWrite($sale, 'PEN', '30.00');
    }

    public function testCashCollectedAmountOnlyAddsCashLines(): void
    {
        $sale = new SaleRecord();
        $sale->setPayments([
            ['payment_method_id' => 1, 'amount' => '25.00'],
            ['payment_method_id' => 2, 'amount' => '75.00'],
        ]);

        $cashAmount = $this->service->resolveCashCollectedAmount($sale, '100.00');

        $this->assertSame('25.00', $cashAmount);
    }

    private function paymentMethod(int $id): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $reflection = new \ReflectionObject($paymentMethod);
        $property = $reflection->getProperty('id');
        $property->setValue($paymentMethod, $id);

        return $paymentMethod;
    }
}

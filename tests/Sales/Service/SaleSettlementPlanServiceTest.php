<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Contacts\Entity\Customer;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\ExplicitSaleSettlementResolver;
use App\Sales\Service\SaleSettlementPlanService;
use App\Sales\Service\SettlementPaymentMethodResolver;
use Nubit\Platform\Exception\ServiceException;
use PHPUnit\Framework\TestCase;

final class SaleSettlementPlanServiceTest extends TestCase
{
    public function testResolvesCanonicalMixedSettlementContract(): void
    {
        $sale = new SaleRecord();
        $sale->setCustomer($this->identifiedCustomer());
        $sale->setSettlements([
            [
                'type' => 'PAYMENT',
                'payment_method_id' => 1,
                'amount' => '40.00',
                'tendered_amount' => '50.00',
                'change_amount' => '10.00',
            ],
            [
                'type' => 'RECEIVABLE',
                'amount' => '60.00',
            ],
        ]);

        $plan = new SaleSettlementPlanService()->resolveForWrite($sale, 'PEN', '100.00');

        static::assertSame('PAYMENT', $plan[0]['type']);
        static::assertSame(1, $plan[0]['payment_method_id']);
        static::assertSame('40.00', $plan[0]['amount']);
        static::assertSame('RECEIVABLE', $plan[1]['type']);
        static::assertNull($plan[1]['payment_method_id']);
        static::assertSame('60.00', $plan[1]['amount']);
    }

    public function testCanonicalReceivableCannotMasqueradeAsPaymentMethod(): void
    {
        $sale = new SaleRecord();
        $sale->setSettlements([[
            'type' => 'RECEIVABLE',
            'payment_method_id' => PaymentMethod::CREDIT,
            'amount' => '100.00',
        ]]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('no debe incluir método de pago');

        new SaleSettlementPlanService()->resolveForWrite($sale, 'PEN', '100.00');
    }

    public function testReceivableCatalogueOptionCannotBeSubmittedAsCollectedPayment(): void
    {
        $credit = $this->paymentMethod(PaymentMethod::CREDIT)->setSettlementBehavior(SettlementBehavior::RECEIVABLE);
        $repository = $this->createMock(PaymentMethodRepository::class);
        $repository->expects($this->once())->method('find')->with(PaymentMethod::CREDIT)->willReturn($credit);
        $service = new SaleSettlementPlanService(new ExplicitSaleSettlementResolver(
            new SettlementPaymentMethodResolver($repository),
        ));
        $sale = new SaleRecord();
        $sale->setSettlements([[
            'type' => 'PAYMENT',
            'payment_method_id' => PaymentMethod::CREDIT,
            'amount' => '100.00',
        ]]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('no puede registrarse como pago recibido');

        $service->resolveForWrite($sale, 'PEN', '100.00');
    }

    public function testCanonicalReceivableRequiresIdentifiedCustomer(): void
    {
        $sale = new SaleRecord();
        $sale->setCustomer(
            new Customer()
                ->setRuc('00000000')
                ->setBusinessName('Cliente General'),
        );
        $sale->setSettlements([[
            'type' => 'RECEIVABLE',
            'amount' => '100.00',
        ]]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('cliente identificado');

        new SaleSettlementPlanService()->resolveForWrite($sale, 'PEN', '100.00');
    }

    public function testLegacyCreditMethodIsAdaptedToReceivable(): void
    {
        $sale = new SaleRecord();
        $sale->setPayments([[
            'payment_method_id' => PaymentMethod::CREDIT,
            'amount' => '100.00',
        ]]);

        $plan = new SaleSettlementPlanService()->resolveForWrite($sale, 'PEN', '100.00');

        static::assertSame('RECEIVABLE', $plan[0]['type']);
        static::assertNull($plan[0]['payment_method_id']);
    }

    public function testLegacyCreditAndPartialFlagBecomeOneReceivableAllocation(): void
    {
        $sale = new SaleRecord();
        $sale->setCreditSale(true);
        $sale->setPayments([
            [
                'payment_method_id' => PaymentMethod::CASH,
                'amount' => '20.00',
            ],
            [
                'payment_method_id' => PaymentMethod::CREDIT,
                'amount' => '30.00',
            ],
        ]);

        $plan = new SaleSettlementPlanService()->resolveForWrite($sale, 'PEN', '100.00');

        static::assertCount(2, $plan);
        static::assertSame('PAYMENT', $plan[0]['type']);
        static::assertSame('20.00', $plan[0]['amount']);
        static::assertSame('RECEIVABLE', $plan[1]['type']);
        static::assertSame('80.00', $plan[1]['amount']);
    }

    private function paymentMethod(int $id): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $reflection = new \ReflectionObject($paymentMethod);
        $property = $reflection->getProperty('id');
        $property->setValue($paymentMethod, $id);

        return $paymentMethod;
    }

    private function identifiedCustomer(): Customer
    {
        return new Customer()
            ->setRuc('20123456789')
            ->setBusinessName('Cliente crédito');
    }
}

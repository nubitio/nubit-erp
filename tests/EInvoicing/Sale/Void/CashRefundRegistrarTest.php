<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Sale\Void;

use App\Entity\CashMovement;
use App\Entity\CashMovementCategory;
use App\Entity\CashShift;
use App\Entity\PaymentMethod;
use App\Legacy\Sale;
use App\Enum\CashMovementType;
use App\Enum\CashShiftStatus;
use App\EInvoicing\Sale\Void\CashRefundRegistrar;
use App\Repository\CashMovementCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CashRefundRegistrarTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Happy path: open shift + cash payment → CashMovement persisted
    // ---------------------------------------------------------------------------

    public function testPersistsCashMovementWhenOpenShiftAndCashPayment(): void
    {
        $paymentMethod = $this->makeCashPaymentMethod();

        $sale = $this->makeSale(id: 'sale-001', total: '150.00', serie: 'B001', numero: '100');
        $sale->setTipoPago($paymentMethod);

        $cashShift = $this->createMock(CashShift::class);

        $category = $this->makeCashRefundCategory();

        $shiftRepository = $this->createMock(EntityRepository::class);
        $shiftRepository->method('findOneBy')->willReturn($cashShift);

        $persistedEntity = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($shiftRepository);
        $em->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedEntity): void {
                $persistedEntity = $entity;
            });

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn($category);

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());
        $registrar->register($sale, 1);

        self::assertInstanceOf(CashMovement::class, $persistedEntity);
        /** @var CashMovement $persistedEntity */
        self::assertSame('150.00', $persistedEntity->getAmount());
        self::assertStringContainsString('B001', $persistedEntity->getDescription());
        self::assertStringContainsString('100', $persistedEntity->getDescription());
    }

    public function testCashMovementIsLinkedToCashShift(): void
    {
        $paymentMethod = $this->makeCashPaymentMethod();

        $sale = $this->makeSale(id: 'sale-shift', total: '50.00', serie: 'B001', numero: '2');
        $sale->setTipoPago($paymentMethod);

        $cashShift = $this->createMock(CashShift::class);

        $category = $this->makeCashRefundCategory();

        $shiftRepository = $this->createMock(EntityRepository::class);
        $shiftRepository->method('findOneBy')->willReturn($cashShift);

        /** @var CashMovement|null $persistedMovement */
        $persistedMovement = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($shiftRepository);
        $em->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedMovement): void {
                if ($entity instanceof CashMovement) {
                    $persistedMovement = $entity;
                }
            });

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn($category);

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());
        $registrar->register($sale, 1);

        self::assertNotNull($persistedMovement);
        self::assertSame($cashShift, $persistedMovement->getCashShift());
    }

    public function testCashMovementTypeMatchesCategoryType(): void
    {
        $paymentMethod = $this->makeCashPaymentMethod();

        $sale = $this->makeSale(id: 'sale-type', total: '75.00', serie: 'B001', numero: '3');
        $sale->setTipoPago($paymentMethod);

        $cashShift = $this->createMock(CashShift::class);
        $category = $this->makeCashRefundCategory(type: CashMovementType::EXPENSE);

        $shiftRepository = $this->createMock(EntityRepository::class);
        $shiftRepository->method('findOneBy')->willReturn($cashShift);

        /** @var CashMovement|null $persistedMovement */
        $persistedMovement = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($shiftRepository);
        $em->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedMovement): void {
                if ($entity instanceof CashMovement) {
                    $persistedMovement = $entity;
                }
            });

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn($category);

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());
        $registrar->register($sale, 1);

        self::assertNotNull($persistedMovement);
        self::assertSame(CashMovementType::EXPENSE, $persistedMovement->getType());
    }

    // ---------------------------------------------------------------------------
    // No open cash shift → graceful skip, no persist, no exception
    // ---------------------------------------------------------------------------

    public function testGracefullySkipsWhenNoOpenCashShift(): void
    {
        $paymentMethod = $this->makeCashPaymentMethod();

        $sale = $this->makeSale(id: 'sale-noshift', total: '200.00');
        $sale->setTipoPago($paymentMethod);

        $shiftRepository = $this->createMock(EntityRepository::class);
        $shiftRepository->method('findOneBy')->willReturn(null); // no open shift

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($shiftRepository);
        $em->expects(self::never())->method('persist');

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());

        // Must not throw
        $registrar->register($sale, 1);
    }

    public function testNoExceptionThrownWhenNoOpenCashShift(): void
    {
        $paymentMethod = $this->makeCashPaymentMethod();

        $sale = $this->makeSale(id: 'sale-noshift-ok', total: '100.00');
        $sale->setTipoPago($paymentMethod);

        $shiftRepository = $this->createMock(EntityRepository::class);
        $shiftRepository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($shiftRepository);

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());

        $this->expectNotToPerformAssertions();
        $registrar->register($sale, 42);
    }

    // ---------------------------------------------------------------------------
    // Sale has no cash payment → immediate return, no EM interaction for shift
    // ---------------------------------------------------------------------------

    public function testDoesNothingWhenSaleHasNoCashPaymentMethod(): void
    {
        $sale = $this->makeSale(id: 'sale-nocash', total: '300.00');
        // No tipo_pago set → getTipoPago() returns null → early return

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $em->expects(self::never())->method('persist');

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());
        $registrar->register($sale, 1);
    }

    public function testDoesNothingWhenSaleHasNonCashPaymentMethod(): void
    {
        // PaymentMethod with id != PaymentMethod::CASH (e.g. credit card = 2)
        $paymentMethod = new PaymentMethod();
        $prop = (new \ReflectionClass(PaymentMethod::class))->getProperty('id');
        $prop->setValue($paymentMethod, 2); // credit card

        $sale = $this->makeSale(id: 'sale-card', total: '500.00');
        $sale->setTipoPago($paymentMethod);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $em->expects(self::never())->method('persist');

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());
        $registrar->register($sale, 1);
    }

    // ---------------------------------------------------------------------------
    // Missing refund category → graceful skip
    // ---------------------------------------------------------------------------

    public function testGracefullySkipsWhenRefundCategoryNotFound(): void
    {
        $paymentMethod = $this->makeCashPaymentMethod();

        $sale = $this->makeSale(id: 'sale-nocat', total: '80.00');
        $sale->setTipoPago($paymentMethod);

        $cashShift = $this->createMock(CashShift::class);

        $shiftRepository = $this->createMock(EntityRepository::class);
        $shiftRepository->method('findOneBy')->willReturn($cashShift);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($shiftRepository);
        $em->expects(self::never())->method('persist');

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn(null); // category missing

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());

        // Must not throw
        $registrar->register($sale, 1);
    }

    // ---------------------------------------------------------------------------
    // Shift lookup uses correct criteria (OPEN status + userId)
    // ---------------------------------------------------------------------------

    public function testShiftLookupUsesOpenStatusAndUserId(): void
    {
        $paymentMethod = $this->makeCashPaymentMethod();

        $sale = $this->makeSale(id: 'sale-lookup', total: '10.00');
        $sale->setTipoPago($paymentMethod);

        $capturedCriteria = null;

        $shiftRepository = $this->createMock(EntityRepository::class);
        $shiftRepository->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use (&$capturedCriteria): null {
                $capturedCriteria = $criteria;

                return null;
            });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($shiftRepository);

        $categoryRepository = $this->createMock(CashMovementCategoryRepository::class);

        $registrar = new CashRefundRegistrar($em, $categoryRepository, new NullLogger());
        $registrar->register($sale, 99);

        self::assertIsArray($capturedCriteria);
        self::assertSame(CashShiftStatus::OPEN, $capturedCriteria['status'] ?? null);
        self::assertSame(99, $capturedCriteria['openedBy'] ?? null);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeCashPaymentMethod(): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $prop = (new \ReflectionClass(PaymentMethod::class))->getProperty('id');
        $prop->setValue($paymentMethod, PaymentMethod::CASH);

        return $paymentMethod;
    }

    private function makeSale(
        string $id,
        string $total = '100.00',
        string $serie = 'B001',
        string $numero = '1',
    ): Sale {
        $sale = new Sale();
        $sale->setId($id);
        $sale->setTotal($total);
        $sale->setSerie($serie);
        $sale->setNumero($numero);

        return $sale;
    }

    private function makeCashRefundCategory(CashMovementType $type = CashMovementType::EXPENSE): CashMovementCategory
    {
        $category = new CashMovementCategory();
        $category->setCode(CashMovementCategory::CUSTOMER_REFUND);
        $category->setName('Reembolso cliente');
        $category->setType($type);

        return $category;
    }
}

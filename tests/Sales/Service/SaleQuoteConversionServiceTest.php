<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Contacts\Entity\Customer;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Sales\Dto\SaleQuoteConvertDto;
use App\Sales\Entity\SaleQuote;
use App\Sales\Entity\SaleQuoteItem;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SaleQuoteConversionService;
use App\Sales\State\SaleUtils;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class SaleQuoteConversionServiceTest extends TestCase
{
    public function testConvertThrowsWhenAlreadyConverted(): void
    {
        $quote = new SaleQuote()->setStatus(SaleQuote::STATUS_CONVERTED);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = $this->makeService($entityManager);

        $this->expectException(ValidationException::class);
        $service->convert($quote, new SaleQuoteConvertDto(id: (string) $quote->getId()), new User());
    }

    public function testConvertThrowsWhenValidUntilAlreadyPassedEvenIfStatusIsDraft(): void
    {
        // 2 days, not 1: the expiry check compares against Lima's calendar
        // date, which can differ from UTC "now"'s date by up to a day near
        // the boundary — a 1-day margin would be flaky depending on the
        // hour the suite runs.
        $quote = new SaleQuote()->setValidUntil(new DateTimeImmutable('-2 days'));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = $this->makeService($entityManager);

        $this->expectException(ValidationException::class);
        $service->convert($quote, new SaleQuoteConvertDto(id: 'x'), new User());
    }

    public function testConvertThrowsWhenNoItems(): void
    {
        $quote = new SaleQuote();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = $this->makeService($entityManager);

        $this->expectException(ValidationException::class);
        $service->convert($quote, new SaleQuoteConvertDto(id: 'x'), new User());
    }

    public function testConvertThrowsWhenNoWarehouseResolvable(): void
    {
        $quote = new SaleQuote();
        $quote->addItem(
            new SaleQuoteItem()
                ->setNetAmount('10.00')
                ->setGrossAmount('10.00')
                ->setDiscountAmount('0.00')
                ->setTaxAmount('0.00'),
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = $this->makeService($entityManager);

        $this->expectException(ValidationException::class);
        $service->convert($quote, new SaleQuoteConvertDto(id: 'x'), new User());
    }

    public function testConvertHappyPathBuildsSaleRecordAndMarksQuoteConverted(): void
    {
        $quote = new SaleQuote()
            ->setWarehouse(new Warehouse())
            ->setBranch(new Branch())
            ->setCustomer(new Customer())
            ->setCurrency('PEN');
        $quote->addItem(
            new SaleQuoteItem()
                ->setLineNo(1)
                ->setQty('2.000')
                ->setUnitPrice('10.0000')
                ->setGrossAmount('20.00')
                ->setDiscountAmount('0.00')
                ->setTaxAmount('0.00')
                ->setNetAmount('20.00')
                ->setNameSnapshot('Producto de prueba'),
        );

        $paymentMethod = new PaymentMethod();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(PaymentMethod::class, PaymentMethod::CASH)->willReturn($paymentMethod);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(SaleRecord::class));
        $entityManager->expects(self::once())->method('flush');
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn(callable $callback) => $callback());

        $saleUtils = $this->createMock(SaleUtils::class);
        $saleUtils->method('fillSaleValues')->willReturnCallback(static fn(SaleRecord $sale) => $sale);

        $service = new SaleQuoteConversionService($entityManager, $saleUtils, new ActiveReferenceGuard());

        $sale = $service->convert($quote, new SaleQuoteConvertDto(id: 'x'), new User());

        self::assertSame('20.00', $sale->getGrandTotal());
        self::assertSame(SaleQuote::STATUS_CONVERTED, $quote->getStatus());
        self::assertNotNull($quote->getConvertedAt());
    }

    private function makeService(EntityManagerInterface $entityManager): SaleQuoteConversionService
    {
        $saleUtils = $this->createMock(SaleUtils::class);

        return new SaleQuoteConversionService($entityManager, $saleUtils, new ActiveReferenceGuard());
    }
}

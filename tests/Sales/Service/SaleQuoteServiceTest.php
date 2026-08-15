<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Entity\SaleQuote;
use App\Sales\Entity\SaleQuoteItem;
use App\Sales\Service\SaleQuoteService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class SaleQuoteServiceTest extends TestCase
{
    public function testRecomputeTotalsSumsItemAmounts(): void
    {
        $quote = new SaleQuote();
        $quote->addItem(
            new SaleQuoteItem()
                ->setGrossAmount('100.00')
                ->setDiscountAmount('10.00')
                ->setTaxAmount('0.00')
                ->setNetAmount('90.00'),
        );
        $quote->addItem(
            new SaleQuoteItem()
                ->setGrossAmount('50.00')
                ->setDiscountAmount('0.00')
                ->setTaxAmount('0.00')
                ->setNetAmount('50.00'),
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        new SaleQuoteService($entityManager)->recomputeTotals($quote);

        self::assertSame('150.00', $quote->getSubtotal());
        self::assertSame('10.00', $quote->getDiscountTotal());
        self::assertSame('0.00', $quote->getTaxTotal());
        self::assertSame('140.00', $quote->getGrandTotal());
    }

    public function testTransitionToAllowsForwardTransition(): void
    {
        $quote = new SaleQuote();
        self::assertSame(SaleQuote::STATUS_DRAFT, $quote->getStatus());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        new SaleQuoteService($entityManager)->transitionTo($quote, SaleQuote::STATUS_SENT);

        self::assertSame(SaleQuote::STATUS_SENT, $quote->getStatus());
    }

    public function testTransitionToRejectsInvalidTransition(): void
    {
        $quote = new SaleQuote()->setStatus(SaleQuote::STATUS_CONVERTED);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->expectException(ValidationException::class);
        new SaleQuoteService($entityManager)->transitionTo($quote, SaleQuote::STATUS_DRAFT);
    }

    public function testTransitionToRejectsBackwardTransition(): void
    {
        $quote = new SaleQuote()->setStatus(SaleQuote::STATUS_SENT);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->expectException(ValidationException::class);
        new SaleQuoteService($entityManager)->transitionTo($quote, SaleQuote::STATUS_DRAFT);
    }

    public function testExpireIfPastDueFlipsStatusWhenPastDue(): void
    {
        // 2 days, not 1: expireIfPastDue compares against Lima's calendar
        // date, which can differ from UTC "now"'s date by up to a day near
        // the boundary — a 1-day margin would be flaky depending on the
        // hour the suite runs.
        $quote = new SaleQuote()->setValidUntil(new DateTimeImmutable('-2 days'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $expired = new SaleQuoteService($entityManager)->expireIfPastDue($quote);

        self::assertTrue($expired);
        self::assertSame(SaleQuote::STATUS_EXPIRED, $quote->getStatus());
    }

    public function testExpireIfPastDueDoesNothingWhenNotPastDue(): void
    {
        $quote = new SaleQuote()->setValidUntil(new DateTimeImmutable('+2 days'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $expired = new SaleQuoteService($entityManager)->expireIfPastDue($quote);

        self::assertFalse($expired);
        self::assertSame(SaleQuote::STATUS_DRAFT, $quote->getStatus());
    }

    public function testExpireIfPastDueDoesNothingWhenAlreadyConverted(): void
    {
        $quote = new SaleQuote()
            ->setValidUntil(new DateTimeImmutable('-2 days'))
            ->setStatus(SaleQuote::STATUS_CONVERTED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $expired = new SaleQuoteService($entityManager)->expireIfPastDue($quote);

        self::assertFalse($expired);
        self::assertSame(SaleQuote::STATUS_CONVERTED, $quote->getStatus());
    }

    public function testBuildItemsFromPayloadComputesNetAmountWithoutProduct(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $items = new SaleQuoteService($entityManager)->buildItemsFromPayload([
            ['qty' => 2, 'unitPrice' => '10.00', 'nameSnapshot' => 'Servicio manual'],
        ]);

        self::assertCount(1, $items);
        self::assertSame('20.00', $items[0]->getGrossAmount());
        self::assertSame('0.00', $items[0]->getDiscountAmount());
        self::assertSame('20.00', $items[0]->getNetAmount());
        self::assertSame('Servicio manual', $items[0]->getNameSnapshot());
    }

    public function testBuildItemsFromPayloadAppliesDiscountPercent(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $items = new SaleQuoteService($entityManager)->buildItemsFromPayload([
            ['qty' => 1, 'unitPrice' => '100.00', 'discountPercent' => 10, 'nameSnapshot' => 'Item con descuento'],
        ]);

        self::assertSame('10.00', $items[0]->getDiscountAmount());
        self::assertSame('90.00', $items[0]->getNetAmount());
    }

    public function testBuildItemsFromPayloadRejectsInvalidDiscountPercent(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->expectException(ValidationException::class);
        new SaleQuoteService($entityManager)->buildItemsFromPayload([
            ['qty' => 1, 'unitPrice' => '100.00', 'discountPercent' => 150],
        ]);
    }
}

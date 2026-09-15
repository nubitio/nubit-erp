<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Observability\OperationLog;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\CustomerPaymentApplication;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\ApplyAdvanceService;
use App\Sales\Service\CollectOnAccountPlanner;
use App\Sales\Service\SalePaymentService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
final class ApplyAdvanceServiceTest extends TestCase
{
    /** @var list<EconomicEvent> */
    private array $dispatched = [];
    /** @var list<object> */
    private array $persisted = [];
    private CustomerAccountEntryRepository&MockObject $entries;
    private SaleRecordRepository&MockObject $sales;
    private SalePaymentService&MockObject $salePaymentService;
    private bool $methodConfigured = true;

    protected function setUp(): void
    {
        $this->dispatched = [];
        $this->persisted = [];
        $this->methodConfigured = true;
        $this->entries = $this->createMock(CustomerAccountEntryRepository::class);
        $this->sales = $this->createMock(SaleRecordRepository::class);
        $this->salePaymentService = $this->createMock(SalePaymentService::class);
        $this->salePaymentService
            ->method('appendAdvancePayment')
            ->willReturnCallback(fn(): SalePayment => $this->salePayment(random_int(min: 1, max: 99_999)));
    }

    public function testFifoAppliesAcrossSalesDrawingFromAdvanceEntries(): void
    {
        $this->entries->method('availableAdvance')->with(9)->willReturn('500.00', '200.00');
        $this->entries
            ->method('advancesWithRemaining')
            ->with(9)
            ->willReturn([
                ['entryId' => 11, 'remaining' => '250.00'],
                ['entryId' => 12, 'remaining' => '250.00'],
            ]);
        $this->sales
            ->method('findOpenReceivablesForCustomer')
            ->willReturn([
                $this->sale('s1', '200.00'),
                $this->sale('s2', '150.00'),
            ]);
        $this->salePaymentService->expects(self::exactly(2))->method('appendAdvancePayment');

        $result = $this->service()->apply(9, '300.00', new DateTimeImmutable('2026-09-10'), []);

        static::assertSame('300.00', $result->applied);
        static::assertSame('200.00', $result->availableAdvance);
        static::assertCount(2, $result->applications);
        $applications = array_filter(
            $this->persisted,
            static fn(object $o): bool => $o instanceof CustomerPaymentApplication,
        );
        // s1 (200) fits in entry 11; s2 (100) still fits in entry 11 -> 2 rows.
        static::assertGreaterThanOrEqual(2, count($applications));
        static::assertCount(1, $this->dispatched);
        static::assertSame(EconomicEvent::CUSTOMER_ADVANCE_APPLIED, $this->dispatched[0]->kind);
        static::assertSame('300.00', $this->dispatched[0]->total);
    }

    public function testAmountAboveAvailableAdvanceIsRejected(): void
    {
        $this->entries->method('availableAdvance')->willReturn('100.00');
        $this->salePaymentService->expects(self::never())->method('appendAdvancePayment');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('no alcanza');

        $this->service()->apply(9, '150.00', new DateTimeImmutable(), []);
    }

    public function testTheAdvanceMethodIsCreatedOnFirstUseWhenMissing(): void
    {
        $this->methodConfigured = false; // findOneBy -> null
        $this->entries->method('availableAdvance')->willReturn('500.00', '400.00');
        $this->entries->method('advancesWithRemaining')->willReturn([['entryId' => 1, 'remaining' => '500.00']]);
        $this->sales->method('findOpenReceivablesForCustomer')->willReturn([$this->sale('s1', '100.00')]);

        $this->service()->apply(9, '100.00', new DateTimeImmutable('2026-09-10'), []);

        $created = array_filter(
            $this->persisted,
            static fn(object $o): bool => (
                $o instanceof PaymentMethod
                && $o->getSettlementBehavior() === SettlementBehavior::ADVANCE
                && $o->getIsActive() === false
            ),
        );
        static::assertCount(1, $created);
    }

    public function testNoOpenSalesIsRejected(): void
    {
        $this->entries->method('availableAdvance')->willReturn('500.00');
        $this->sales->method('findOpenReceivablesForCustomer')->willReturn([]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('no tiene comprobantes pendientes');

        $this->service()->apply(9, '100.00', new DateTimeImmutable(), []);
    }

    public function testAmountAboveApplicableDebtIsRejected(): void
    {
        $this->entries->method('availableAdvance')->willReturn('500.00');
        $this->entries->method('advancesWithRemaining')->willReturn([['entryId' => 1, 'remaining' => '500.00']]);
        $this->sales->method('findOpenReceivablesForCustomer')->willReturn([$this->sale('s1', '40.00')]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->service()->apply(9, '100.00', new DateTimeImmutable(), []);
    }

    private function service(): ApplyAdvanceService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn(callable $f): mixed => $f());
        $em->method('persist')->willReturnCallback(function (object $o): void {
            $this->persisted[] = $o;
        });
        $em->method('getReference')->willReturn($this->createStub(CustomerAccountEntry::class));

        $pmRepo = $this->createMock(EntityRepository::class);
        $pmRepo->method('findOneBy')->willReturnCallback(fn(): ?PaymentMethod => $this->methodConfigured
            ? $this->advanceMethod()
            : null);
        $em->method('getRepository')->willReturn($pmRepo);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $m): Envelope {
            if ($m instanceof EconomicEvent) {
                $this->dispatched[] = $m;
            }

            return new Envelope($m);
        });

        return new ApplyAdvanceService(
            $em,
            $this->sales,
            $this->entries,
            new CollectOnAccountPlanner(),
            $this->salePaymentService,
            $bus,
            new OperationLog(new NullLogger()),
        );
    }

    private function sale(string $id, string $balanceDue): SaleRecord
    {
        $sale = new SaleRecord()
            ->setBalanceDue($balanceDue)
            ->setCurrency('PEN')
            ->setSerie('F001')
            ->setNumero(ltrim($id, characters: 's'));
        new \ReflectionProperty(SaleRecord::class, 'id')->setValue($sale, $id);

        return $sale;
    }

    private function salePayment(int $id): SalePayment
    {
        $payment = new SalePayment();
        new \ReflectionProperty(SalePayment::class, 'id')->setValue($payment, $id);

        return $payment;
    }

    private function advanceMethod(): PaymentMethod
    {
        $method = new PaymentMethod()
            ->setSettlementBehavior(SettlementBehavior::ADVANCE)
            ->setIsActive(false);
        new \ReflectionProperty(PaymentMethod::class, 'id')->setValue($method, 3);

        return $method;
    }
}

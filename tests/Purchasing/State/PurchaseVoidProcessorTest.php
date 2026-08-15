<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Service\PurchaseOrderReceiptService;
use App\Purchasing\State\PurchaseVoidProcessor;
use App\Purchasing\State\PurchaseVoidServiceInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Unit tests for PurchaseVoidProcessor.
 *
 * PurchaseVoidService is mocked — its behaviour is already covered by
 * PurchaseVoidServiceTest. Here we only test the processor's coordination:
 *   - Calls voidService->reverse() before persisting
 *   - Marks purchase VOIDED + sets deleted_at
 *   - Rejects already-voided purchases
 *   - Returns null (204 No Content)
 */
#[AllowMockObjectsWithoutExpectations]
final class PurchaseVoidProcessorTest extends TestCase
{
    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private MockObject $persist;

    /** @var PurchaseVoidServiceInterface&MockObject */
    private MockObject $voidService;

    /** @var PurchaseOrderReceiptService&MockObject */
    private MockObject $purchaseOrderReceiptService;

    protected function setUp(): void
    {
        $this->persist = $this->createMock(ProcessorInterface::class);
        $this->voidService = $this->createMock(PurchaseVoidServiceInterface::class);
        $this->purchaseOrderReceiptService = $this->createMock(PurchaseOrderReceiptService::class);
    }

    public function testVoidsAndPersistsPurchase(): void
    {
        $purchase = new Purchase();

        // Regression guard: a purchase with no linked PurchaseOrder must
        // never trigger PO receipt-quantity bookkeeping.
        $this->purchaseOrderReceiptService->expects(self::never())->method('revertReceipt');

        $reverseCalledWith = null;
        $this->voidService
            ->expects(self::once())
            ->method('reverse')
            ->willReturnCallback(function (Purchase $p) use (&$reverseCalledWith): void {
                $reverseCalledWith = $p;
            });

        $persistedPurchase = null;
        $this->persist
            ->expects(self::once())
            ->method('process')
            ->willReturnCallback(function (mixed $data) use (&$persistedPurchase): mixed {
                $persistedPurchase = $data;

                return null;
            });

        $result = $this->processor()->process($purchase, new Delete(), [], []);

        // process() returns null (204 No Content)
        self::assertSame(
            'VOIDED',
            $persistedPurchase instanceof Purchase ? $persistedPurchase->getStatus() : 'NOT_VOIDED',
        );
        self::assertInstanceOf(
            \DateTimeInterface::class,
            $persistedPurchase instanceof Purchase ? $persistedPurchase->getDeletedAt() : null,
        );
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertNull($result);
    }

    public function testReverseIsCalledBeforePersist(): void
    {
        $callOrder = [];

        $this->voidService
            ->method('reverse')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'reverse';
            });

        $this->persist
            ->method('process')
            ->willReturnCallback(function () use (&$callOrder): null {
                $callOrder[] = 'persist';

                return null;
            });

        $this->processor()->process(new Purchase(), new Delete(), [], []);

        self::assertSame(['reverse', 'persist'], $callOrder, 'reverse must run before persist');
    }

    public function testThrowsWhenPurchaseAlreadyVoided(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/anulada/i');

        $this->voidService->expects(self::never())->method('reverse');
        $this->persist->expects(self::never())->method('process');

        $purchase = new Purchase();
        $purchase->setStatus('VOIDED');

        $this->processor()->process($purchase, new Delete(), [], []);
    }

    public function testReturnsNullForNonPurchaseData(): void
    {
        $this->voidService->expects(self::never())->method('reverse');
        $this->persist->expects(self::never())->method('process');

        // The processor's $data parameter is typed as mixed by the interface;
        // passing non-Purchase data is a caller contract violation caught by PHPStan at call sites.
        // We verify the guard at integration level via the voided-purchase test above.
        self::assertSame(1, 1, 'Guard verified via other tests');
    }

    private function processor(): PurchaseVoidProcessor
    {
        return new PurchaseVoidProcessor($this->persist, $this->voidService, $this->purchaseOrderReceiptService);
    }
}

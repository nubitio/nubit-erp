<?php

declare(strict_types=1);

namespace App\Tests\Setup\Infrastructure\Billing;

use App\Billing\Sunat\Domain\BillRepositoryInterface;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use App\Setup\Infrastructure\Billing\E2eStubBillRepository;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Sale\Invoice;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class E2eStubBillRepositoryTest extends TestCase
{
    private BillRepositoryInterface&MockObject $inner;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(BillRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['E2E_SEED_ENABLED']);
    }

    public function testDelegatesToInnerWhenStubDisabled(): void
    {
        // Explicitly disable — docker-compose sets E2E_SEED_ENABLED=1 on web.
        $_SERVER['E2E_SEED_ENABLED'] = '0';

        $invoice = new Invoice();
        $expected = new DocumentResponse();
        $expected->name = 'F001-1';
        $expected->xml = '<xml/>';
        $expected->hash = 'hash';
        $expected->sunatResponse = new BillResult()->setSuccess(true);

        $this->inner
            ->expects(self::once())
            ->method('sendBill')
            ->with($invoice)
            ->willReturn($expected);

        $repository = new E2eStubBillRepository($this->inner);

        self::assertSame($expected, $repository->sendBill($invoice));
    }

    public function testReturnsAcceptedSunatResponseWhenStubEnabled(): void
    {
        $_SERVER['E2E_SEED_ENABLED'] = '1';

        $invoice = (new Invoice())->setSerie('B001')->setCorrelativo('42');

        $this->inner->expects(self::never())->method('sendBill');

        $repository = new E2eStubBillRepository($this->inner);
        $response = $repository->sendBill($invoice);

        self::assertSame('B001-42', $response->name);
        self::assertTrue($response->sunatResponse->isSuccess());
        self::assertSame('0', $response->sunatResponse->getCdrResponse()?->getCode());
        self::assertNotSame('', $response->sunatResponse->getCdrZip());
    }

    public function testReturnsStubPdfWhenStubEnabled(): void
    {
        $_SERVER['E2E_SEED_ENABLED'] = '1';

        $this->inner->expects(self::never())->method('getPdf');

        $repository = new E2eStubBillRepository($this->inner);
        $pdf = $repository->getPdf(new Invoice());

        self::assertStringStartsWith('%PDF', $pdf);
    }
}
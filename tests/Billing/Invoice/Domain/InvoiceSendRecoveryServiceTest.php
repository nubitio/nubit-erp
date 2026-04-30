<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Domain;

use App\Billing\Invoice\Domain\InvoiceConsultUseCase;
use App\Billing\Invoice\Domain\InvoiceSendRecoveryService;
use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Response\StatusCdrResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class InvoiceSendRecoveryServiceTest extends TestCase
{
    private function makeConsultResult(bool $success, bool $accepted = false): StatusCdrResult
    {
        $cdr = $this->createStub(CdrResponse::class);
        $cdr->method('isAccepted')->willReturn($accepted);

        $result = $this->createStub(StatusCdrResult::class);
        $result->method('isSuccess')->willReturn($success);
        $result->method('getCdrResponse')->willReturn($success ? $cdr : null);

        return $result;
    }

    public function testReturnsTrueWhenConsultSucceedsAndCdrIsAccepted(): void
    {
        $consultResult = $this->makeConsultResult(true, true);

        $consultUseCase = $this->createStub(InvoiceConsultUseCase::class);
        $consultUseCase->method('__invoke')->willReturn($consultResult);

        $service = new InvoiceSendRecoveryService($consultUseCase, $this->createStub(LoggerInterface::class));

        self::assertTrue($service->tryRecoverByConsult('doc-1', 'tenant-1'));
    }

    public function testReturnsFalseWhenConsultSucceedsButCdrIsRejected(): void
    {
        $consultResult = $this->makeConsultResult(true, false);

        $consultUseCase = $this->createStub(InvoiceConsultUseCase::class);
        $consultUseCase->method('__invoke')->willReturn($consultResult);

        $service = new InvoiceSendRecoveryService($consultUseCase, $this->createStub(LoggerInterface::class));

        self::assertFalse($service->tryRecoverByConsult('doc-2', 'tenant-1'));
    }

    public function testReturnsFalseWhenConsultFails(): void
    {
        $consultResult = $this->makeConsultResult(false);

        $consultUseCase = $this->createStub(InvoiceConsultUseCase::class);
        $consultUseCase->method('__invoke')->willReturn($consultResult);

        $service = new InvoiceSendRecoveryService($consultUseCase, $this->createStub(LoggerInterface::class));

        self::assertFalse($service->tryRecoverByConsult('doc-3', null));
    }

    public function testReturnsFalseAndLogsWhenConsultThrows(): void
    {
        $consultUseCase = $this->createStub(InvoiceConsultUseCase::class);
        $consultUseCase->method('__invoke')->willThrowException(new \RuntimeException('SUNAT down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with('SUNAT consult recovery failed after send exception', self::isArray());

        $service = new InvoiceSendRecoveryService($consultUseCase, $logger);

        self::assertFalse($service->tryRecoverByConsult('doc-4', 'tenant-x'));
    }
}

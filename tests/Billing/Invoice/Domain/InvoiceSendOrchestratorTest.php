<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Domain;

use App\Billing\Invoice\Domain\InvoiceSendOrchestrator;
use App\Billing\Invoice\Domain\InvoiceSendRecoveryService;
use App\Billing\Invoice\Domain\InvoiceSendRetryService;
use App\Billing\Invoice\Domain\InvoiceSendUseCase;
use App\Billing\Invoice\Domain\SunatSendControlService;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\Error;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class InvoiceSendOrchestratorTest extends TestCase
{
    private InvoiceSendUseCase&MockObject $invoiceSendUseCase;
    private InvoiceSendRetryService&MockObject $retryService;
    private SunatSendControlService&MockObject $controlService;
    private InvoiceSendRecoveryService&MockObject $recoveryService;
    private LoggerInterface&MockObject $logger;
    private InvoiceSendOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->invoiceSendUseCase = $this->createMock(InvoiceSendUseCase::class);
        $this->retryService = $this->createMock(InvoiceSendRetryService::class);
        $this->controlService = $this->createMock(SunatSendControlService::class);
        $this->recoveryService = $this->createMock(InvoiceSendRecoveryService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->orchestrator = new InvoiceSendOrchestrator(
            $this->invoiceSendUseCase,
            $this->retryService,
            $this->controlService,
            $this->recoveryService,
            $this->logger,
        );
    }

    public function testSkipsByBackoffWhenRetryIsNotEligible(): void
    {
        $this->retryService
            ->expects(self::once())
            ->method('evaluateRetryEligibility')
            ->willReturn([
                'eligible' => false,
                'reason' => 'backoff',
                'attempts' => 2,
                'wait_seconds' => 30,
            ]);
        $this->retryService->expects(self::once())->method('recordRetrySkipEvent')->with('doc-1', 'backoff', 2, 30);
        $this->invoiceSendUseCase->expects(self::never())->method('__invoke');

        $result = $this->orchestrator->process(
            'doc-1',
            ['max_attempts' => 8, 'base_seconds' => 60, 'max_seconds' => 1800],
            3,
            'tenant-a',
        );

        self::assertFalse($result->sent);
        self::assertFalse($result->paused);
        self::assertNull($result->error);
        self::assertSame('backoff', $result->skipReason);
    }

    public function testMarksSentWhenSendSucceeds(): void
    {
        $this->retryService
            ->method('evaluateRetryEligibility')
            ->willReturn([
                'eligible' => true,
                'reason' => 'eligible',
                'attempts' => 0,
                'wait_seconds' => 0,
            ]);
        $this->invoiceSendUseCase->method('__invoke')->willReturn($this->buildDocumentResponse(success: true));
        $this->retryService->method('getCurrentStatus')->willReturn('pendiente');
        $this->controlService->expects(self::once())->method('resetRejectionStreak')->with('tenant-a');

        $result = $this->orchestrator->process(
            'doc-2',
            ['max_attempts' => 8, 'base_seconds' => 60, 'max_seconds' => 1800],
            3,
            'tenant-a',
        );

        self::assertTrue($result->sent);
        self::assertFalse($result->paused);
        self::assertNull($result->error);
        self::assertNull($result->skipReason);
    }

    public function testHandlesNonRejectionErrorWithoutPause(): void
    {
        $this->retryService
            ->method('evaluateRetryEligibility')
            ->willReturn([
                'eligible' => true,
                'reason' => 'eligible',
                'attempts' => 1,
                'wait_seconds' => 0,
            ]);
        $this->invoiceSendUseCase
            ->method('__invoke')
            ->willReturn($this->buildDocumentResponse(success: false, errorCode: '500', errorMessage: 'Bad request'));
        $this->retryService->method('getCurrentStatus')->willReturn('500');
        $this->controlService
            ->expects(self::once())
            ->method('isSunatRejection')
            ->with('500', 'Bad request')
            ->willReturn(false);
        $this->controlService->expects(self::once())->method('resetRejectionStreak')->with('tenant-a');
        $this->controlService->expects(self::never())->method('pauseSunatSendingAfterRejection');

        $result = $this->orchestrator->process(
            'doc-3',
            ['max_attempts' => 8, 'base_seconds' => 60, 'max_seconds' => 1800],
            3,
            'tenant-a',
        );

        self::assertFalse($result->sent);
        self::assertFalse($result->paused);
        self::assertSame('Bad request', $result->error);
        self::assertNull($result->skipReason);
    }

    public function testPausesWhenRejectionReachesThreshold(): void
    {
        $this->retryService
            ->method('evaluateRetryEligibility')
            ->willReturn([
                'eligible' => true,
                'reason' => 'eligible',
                'attempts' => 2,
                'wait_seconds' => 0,
            ]);
        $this->invoiceSendUseCase
            ->method('__invoke')
            ->willReturn($this->buildDocumentResponse(success: false, errorCode: '3462', errorMessage: 'Rechazado'));
        $this->retryService->method('getCurrentStatus')->willReturn('3462');
        $this->controlService->method('isSunatRejection')->willReturn(true);
        $this->controlService
            ->expects(self::once())
            ->method('incrementRejectionStreak')
            ->with('tenant-a')
            ->willReturn(3);
        $this->controlService
            ->expects(self::once())
            ->method('pauseSunatSendingAfterRejection')
            ->with('doc-4', '3462', 'Rechazado', 3, 3, 'tenant-a');

        $result = $this->orchestrator->process(
            'doc-4',
            ['max_attempts' => 8, 'base_seconds' => 60, 'max_seconds' => 1800],
            3,
            'tenant-a',
        );

        self::assertFalse($result->sent);
        self::assertTrue($result->paused);
        self::assertStringContainsString('paused', (string) $result->error);
    }

    public function testRecoversByConsultWhenSendThrows(): void
    {
        $this->retryService
            ->method('evaluateRetryEligibility')
            ->willReturn([
                'eligible' => true,
                'reason' => 'eligible',
                'attempts' => 1,
                'wait_seconds' => 0,
            ]);
        $this->invoiceSendUseCase->method('__invoke')->willThrowException(new \RuntimeException('Transport down'));
        $this->recoveryService
            ->expects(self::once())
            ->method('tryRecoverByConsult')
            ->with('doc-5', 'tenant-a')
            ->willReturn(true);
        $this->controlService->expects(self::once())->method('resetRejectionStreak')->with('tenant-a');

        $result = $this->orchestrator->process(
            'doc-5',
            ['max_attempts' => 8, 'base_seconds' => 60, 'max_seconds' => 1800],
            3,
            'tenant-a',
        );

        self::assertTrue($result->sent);
        self::assertFalse($result->paused);
        self::assertNull($result->error);
    }

    public function testReturnsErrorWhenSendThrowsAndRecoveryFails(): void
    {
        $this->retryService
            ->method('evaluateRetryEligibility')
            ->willReturn([
                'eligible' => true,
                'reason' => 'eligible',
                'attempts' => 1,
                'wait_seconds' => 0,
            ]);
        $this->invoiceSendUseCase->method('__invoke')->willThrowException(new \RuntimeException('Timeout'));
        $this->recoveryService->method('tryRecoverByConsult')->willReturn(false);
        $this->controlService->expects(self::never())->method('resetRejectionStreak');

        $result = $this->orchestrator->process(
            'doc-6',
            ['max_attempts' => 8, 'base_seconds' => 60, 'max_seconds' => 1800],
            3,
            'tenant-a',
        );

        self::assertFalse($result->sent);
        self::assertFalse($result->paused);
        self::assertSame('Timeout', $result->error);
    }

    private function buildDocumentResponse(
        bool $success,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): DocumentResponse {
        $billResult = new BillResult()->setSuccess($success);
        if (!$success) {
            $billResult->setError(new Error($errorCode, $errorMessage));
        }

        $response = new DocumentResponse();
        $response->name = 'doc';
        $response->xml = '<xml/>';
        $response->hash = 'hash';
        $response->sunatResponse = $billResult;

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Billing\MessageHandler;

use App\Billing\Message\SendInvoiceMessage;
use App\Billing\MessageHandler\SendInvoiceMessageHandler;
use App\Billing\Sunat\Domain\BillSendUseCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * AC-5: tenant_id from message appears in every log context entry.
 * AC-7: When getTenantId() returns empty string, log context has tenant_id = 'unknown'.
 */
#[AllowMockObjectsWithoutExpectations]
final class SendInvoiceMessageHandlerTest extends TestCase
{
    private BillSendUseCase&Stub $billSendUseCase;
    private LoggerInterface&MockObject $logger;
    private MessageBusInterface&Stub $bus;

    protected function setUp(): void
    {
        $this->billSendUseCase = $this->createStub(BillSendUseCase::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->bus = $this->createStub(MessageBusInterface::class);
        $this->bus->method('dispatch')->willReturnCallback(static fn (object $m) => new Envelope($m));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeHandler(): SendInvoiceMessageHandler
    {
        return new SendInvoiceMessageHandler(billSendUseCase: $this->billSendUseCase, billingLogger: $this->logger, messageBus: $this->bus);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AC-5 tests
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * AC-5: On a successful invocation, both 'send_invoice.start' and 'send_invoice.done'
     *       log entries contain the tenant_id from the message.
     */
    public function testInvokeLogsTenantIdOnSuccess(): void
    {
        $message = new SendInvoiceMessage('doc-001', 'tenant-acme');

        $loggedContexts = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(static function (string $event, array $context) use (&$loggedContexts): void {
                $loggedContexts[$event] = $context;
            });

        $this->makeHandler()->__invoke($message);

        $this->assertArrayHasKey('send_invoice.start', $loggedContexts, 'send_invoice.start must be logged');
        $this->assertArrayHasKey('send_invoice.done', $loggedContexts, 'send_invoice.done must be logged');

        $this->assertSame(
            'tenant-acme',
            $loggedContexts['send_invoice.start']['tenant_id'],
            'send_invoice.start must carry tenant_id from the message',
        );
        $this->assertSame(
            'tenant-acme',
            $loggedContexts['send_invoice.done']['tenant_id'],
            'send_invoice.done must carry tenant_id from the message',
        );
    }

    /**
     * AC-5: On a failed invocation, the 'send_invoice.failed' error log entry
     *       also contains the tenant_id from the message.
     */
    public function testInvokeLogsTenantIdOnFailure(): void
    {
        $message = new SendInvoiceMessage('doc-002', 'tenant-beta');
        $exception = new \RuntimeException('SUNAT unreachable');

        $this->billSendUseCase->method('__invoke')->willThrowException($exception);

        $capturedErrorContext = null;
        $this->logger
            ->method('error')
            ->willReturnCallback(static function (string $event, array $context) use (&$capturedErrorContext): void {
                $capturedErrorContext = $context;
            });

        try {
            $this->makeHandler()->__invoke($message);
        } catch (\RuntimeException) {
            // expected re-throw
        }

        $this->assertNotNull($capturedErrorContext, 'send_invoice.failed must be logged on exception');
        $this->assertSame(
            [
                'tenant_id' => 'tenant-beta',
                'tax_document_id' => 'doc-002',
                'error' => 'SUNAT unreachable',
                'exception' => $exception,
            ],
            $capturedErrorContext,
        );
    }

    /**
     * AC-5: tax_document_id from the message is present in the start log entry.
     */
    public function testInvokeLogsTaxDocumentIdInStartEntry(): void
    {
        $message = new SendInvoiceMessage('doc-xyz-789', 'tenant-gamma');

        $capturedStartContext = null;
        $this->logger
            ->method('info')
            ->willReturnCallback(static function (string $event, array $context) use (&$capturedStartContext): void {
                if ($event === 'send_invoice.start') {
                    $capturedStartContext = $context;
                }
            });

        $this->makeHandler()->__invoke($message);

        $this->assertNotNull($capturedStartContext);
        $this->assertSame('doc-xyz-789', $capturedStartContext['tax_document_id']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AC-7 tests
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * AC-7: When getTenantId() returns empty string, every log entry has tenant_id = 'unknown'.
     */
    public function testInvokeUsesUnknownFallbackWhenTenantIdIsEmpty(): void
    {
        // Empty string tenant_id — simulates CLI / missing tenant context
        $message = new SendInvoiceMessage('doc-no-tenant', '');

        $loggedContexts = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(static function (string $event, array $context) use (&$loggedContexts): void {
                $loggedContexts[$event] = $context;
            });

        $this->makeHandler()->__invoke($message);

        $this->assertSame(
            'unknown',
            $loggedContexts['send_invoice.start']['tenant_id'],
            'tenant_id must be "unknown" when message carries empty string',
        );
        $this->assertSame(
            'unknown',
            $loggedContexts['send_invoice.done']['tenant_id'],
            'tenant_id must be "unknown" in done entry too',
        );
    }

    /**
     * AC-7: When getTenantId() returns empty string and the use case throws,
     *       the error log entry still has tenant_id = 'unknown'.
     */
    public function testInvokeUsesUnknownFallbackInErrorLogWhenTenantIdIsEmpty(): void
    {
        $message = new SendInvoiceMessage('doc-no-tenant-err', '');

        $this->billSendUseCase->method('__invoke')->willThrowException(new \RuntimeException('failure'));

        $capturedErrorContext = null;
        $this->logger
            ->method('error')
            ->willReturnCallback(static function (string $event, array $context) use (&$capturedErrorContext): void {
                $capturedErrorContext = $context;
            });

        try {
            $this->makeHandler()->__invoke($message);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNotNull($capturedErrorContext);
        $this->assertSame(
            'unknown',
            $capturedErrorContext['tenant_id'],
            'tenant_id must be "unknown" in error log when message carries empty string',
        );
    }

    /**
     * AC-5 + AC-7: Exception is re-thrown after logging (handler must not swallow it).
     */
    public function testInvokeRethrowsExceptionAfterLogging(): void
    {
        $message = new SendInvoiceMessage('doc-rethrow', 'tenant-delta');

        $original = new \LogicException('downstream failure');
        $this->billSendUseCase->method('__invoke')->willThrowException($original);

        $this->logger->method('error'); // allow call

        $caught = null;
        try {
            $this->makeHandler()->__invoke($message);
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertSame($original, $caught, 'The exact original exception instance must be re-thrown by the handler');
    }
}

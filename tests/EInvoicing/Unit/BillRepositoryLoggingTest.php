<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Unit;

use Nubit\Platform\Tenant\Context\TenantContext;
use App\EInvoicing\Billing\Infrastructure\BillRepository;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Sale\Invoice;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Asserts structured logging behaviour of BillRepository::sendBill().
 *
 * Covers SPEC-003 AC-2, AC-6, AC-8:
 *   AC-2  — all 6 context keys present; info on success, warning on SUNAT rejection
 *   AC-6  — exception path: error logged with 6 keys AND exception is re-thrown
 *   AC-8  — duration_ms is a non-negative integer
 */
#[AllowMockObjectsWithoutExpectations]
final class BillRepositoryLoggingTest extends TestCase
{
    // ── Mandatory context keys defined by the spec ──────────────────────────
    private const REQUIRED_KEYS = [
        'sunat_code',
        'sunat_message',
        'success',
        'duration_ms',
        'tenant_id',
        'tax_document_id',
    ];

    private SerializerInterface&Stub $serializer;
    private LoggerInterface&Stub $debugLogger;

    protected function setUp(): void
    {
        $this->serializer  = $this->createStub(SerializerInterface::class);
        $this->debugLogger = $this->createStub(LoggerInterface::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a BillRepository wired with the given billingLogger spy and an
     * optional TenantContext (defaults to tenant id 42).
     */
    private function makeRepository(
        LoggerInterface $billingLogger,
        MockHttpClient $httpClient,
        ?TenantContext $tenantContext = null,
    ): BillRepository {
        if ($tenantContext === null) {
            $tenantContext = new TenantContext();
            $tenantContext->setTenant(42, 'acme', 'acme.example.com', 'req-1');
        }

        return new BillRepository(
            $this->serializer,
            $this->debugLogger,
            $billingLogger,
            $httpClient,
            $tenantContext,
            'https://api.example.com/',
            'test-token',
        );
    }

    /**
     * Build a DocumentResponse with a BillResult whose CDR carries the given
     * code/description and success flag.
     */
    private function makeDocumentResponse(
        bool $success,
        string $code = '0',
        string $description = 'La Factura numero F001-1, ha sido aceptada',
    ): DocumentResponse {
        $cdr = (new CdrResponse())
            ->setCode($code)
            ->setDescription($description);

        $billResult = (new BillResult())
            ->setSuccess($success)
            ->setCdrResponse($cdr);

        $doc = new DocumentResponse();
        $doc->name          = 'F001-00000001';
        $doc->xml           = '<xml/>';
        $doc->hash          = 'abc';
        $doc->sunatResponse = $billResult;

        return $doc;
    }

    /**
     * Build a minimal Invoice with serie + correlativo so tax_document_id is
     * deterministic.
     */
    private function makeInvoice(string $serie = 'F001', string $correlativo = '1'): Invoice
    {
        $invoice = new Invoice();
        $invoice->setSerie($serie);
        $invoice->setCorrelativo($correlativo);

        return $invoice;
    }

    // ── AC-2: success path ────────────────────────────────────────────────────

    /**
     * On a successful SUNAT response the billingLogger must receive exactly one
     * `info` call whose context contains all 6 mandatory keys.
     */
    public function testSuccessPathLogsInfoWithAllSixContextKeys(): void
    {
        $capturedLevel   = null;
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedLevel, &$capturedContext): void {
                $capturedLevel   = $level;
                $capturedContext = $context;
            }
        );

        $documentResponse = $this->makeDocumentResponse(success: true, code: '0', description: 'Aceptada');
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient);

        $repo->sendBill($this->makeInvoice());

        self::assertSame(LogLevel::INFO, $capturedLevel, 'Expected info level on SUNAT success');
        self::assertNotNull($capturedContext, 'billingLogger was never called');

        foreach (self::REQUIRED_KEYS as $key) {
            self::assertArrayHasKey($key, $capturedContext, "Missing required context key: {$key}");
        }
    }

    /**
     * On success the context values must match the SUNAT response data and the
     * tenant / document identifiers.
     */
    public function testSuccessPathContextValuesAreCorrect(): void
    {
        /** @var array<string,mixed>|null $capturedContext */
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            }
        );

        $documentResponse = $this->makeDocumentResponse(
            success: true,
            code: '0',
            description: 'Factura aceptada',
        );
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        $tenantContext = new TenantContext();
        $tenantContext->setTenant(7, 'tenant7', 'tenant7.example.com', 'req-7');

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient, $tenantContext);

        $repo->sendBill($this->makeInvoice('F001', '99'));

        self::assertSame('0', $capturedContext['sunat_code']);
        self::assertSame('Factura aceptada', $capturedContext['sunat_message']);
        self::assertTrue($capturedContext['success']);
        self::assertSame('7', $capturedContext['tenant_id']);
        self::assertSame('F001-99', $capturedContext['tax_document_id']);
    }

    // ── AC-2: SUNAT rejection (warning) ──────────────────────────────────────

    /**
     * When SUNAT returns success=false (rejection) the billingLogger must emit
     * a `warning` entry with all 6 context keys.
     */
    public function testSunatRejectionLogsWarningWithAllSixContextKeys(): void
    {
        $capturedLevel   = null;
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedLevel, &$capturedContext): void {
                $capturedLevel   = $level;
                $capturedContext = $context;
            }
        );

        $documentResponse = $this->makeDocumentResponse(
            success: false,
            code: '2800',
            description: 'El documento ya fue enviado anteriormente',
        );
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient);

        $repo->sendBill($this->makeInvoice());

        self::assertSame(LogLevel::WARNING, $capturedLevel, 'Expected warning level on SUNAT rejection');
        self::assertNotNull($capturedContext, 'billingLogger was never called on rejection path');

        foreach (self::REQUIRED_KEYS as $key) {
            self::assertArrayHasKey($key, $capturedContext, "Missing required context key: {$key}");
        }
    }

    /**
     * On SUNAT rejection the context values must reflect the rejection details.
     */
    public function testSunatRejectionContextValuesAreCorrect(): void
    {
        /** @var array<string,mixed>|null $capturedContext */
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            }
        );

        $documentResponse = $this->makeDocumentResponse(
            success: false,
            code: '2800',
            description: 'Documento duplicado',
        );
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient);

        $repo->sendBill($this->makeInvoice('B001', '5'));

        self::assertSame('2800', $capturedContext['sunat_code']);
        self::assertSame('Documento duplicado', $capturedContext['sunat_message']);
        self::assertFalse($capturedContext['success']);
        self::assertSame('B001-5', $capturedContext['tax_document_id']);
    }

    // ── AC-6: exception path ──────────────────────────────────────────────────

    /**
     * When the HTTP call throws, the billingLogger must emit an `error` entry
     * with all 6 mandatory context keys present.
     */
    public function testExceptionPathLogsErrorWithAllSixContextKeys(): void
    {
        $capturedLevel   = null;
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedLevel, &$capturedContext): void {
                $capturedLevel   = $level;
                $capturedContext = $context;
            }
        );

        $this->serializer->method('serialize')->willReturn('{}');

        $networkError = new \RuntimeException('Connection timed out');
        $httpClient   = new MockHttpClient(function () use ($networkError): never {
            throw $networkError;
        });

        $repo = $this->makeRepository($billingLogger, $httpClient);

        try {
            $repo->sendBill($this->makeInvoice());
        } catch (\Throwable) {
            // expected — we only care about the log
        }

        self::assertSame(LogLevel::ERROR, $capturedLevel, 'Expected error level on exception');
        self::assertNotNull($capturedContext, 'billingLogger was never called on exception path');

        foreach (self::REQUIRED_KEYS as $key) {
            self::assertArrayHasKey($key, $capturedContext, "Missing required context key on exception path: {$key}");
        }
    }

    /**
     * On exception success must be false and sunat_code / sunat_message must be
     * present (empty string is acceptable per spec AC-6).
     */
    public function testExceptionPathContextHasCorrectSuccessFlag(): void
    {
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            }
        );

        $this->serializer->method('serialize')->willReturn('{}');

        $httpClient = new MockHttpClient(function (): never {
            throw new \RuntimeException('SSL error');
        });

        $repo = $this->makeRepository($billingLogger, $httpClient);

        try {
            $repo->sendBill($this->makeInvoice());
        } catch (\Throwable) {
        }

        self::assertFalse($capturedContext['success']);
        self::assertIsString($capturedContext['sunat_code']);
        self::assertIsString($capturedContext['sunat_message']);
    }

    /**
     * The exception must be re-thrown after logging so the caller's error
     * handling is not bypassed (AC-6).
     */
    public function testExceptionIsReThrownAfterLogging(): void
    {
        $logWasCalled = false;

        $billingLogger = $this->createSpyLogger(
            function () use (&$logWasCalled): void {
                $logWasCalled = true;
            }
        );

        $this->serializer->method('serialize')->willReturn('{}');

        $original   = new \RuntimeException('Network failure');
        $httpClient = new MockHttpClient(function () use ($original): never {
            throw $original;
        });

        $repo = $this->makeRepository($billingLogger, $httpClient);

        $caught = null;
        try {
            $repo->sendBill($this->makeInvoice());
        } catch (\Throwable $e) {
            $caught = $e;
        }

        self::assertTrue($logWasCalled, 'billingLogger must be called before re-throw');
        self::assertSame($original, $caught, 'The original exception must be re-thrown unchanged');
    }

    // ── AC-8: duration_ms ────────────────────────────────────────────────────

    /**
     * duration_ms must be a non-negative integer on the success path.
     */
    public function testDurationMsIsNonNegativeIntegerOnSuccess(): void
    {
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            }
        );

        $documentResponse = $this->makeDocumentResponse(success: true);
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient);

        $repo->sendBill($this->makeInvoice());

        self::assertIsInt($capturedContext['duration_ms'], 'duration_ms must be an integer');
        self::assertGreaterThanOrEqual(0, $capturedContext['duration_ms'], 'duration_ms must be >= 0');
    }

    /**
     * duration_ms must be a non-negative integer on the exception path.
     */
    public function testDurationMsIsNonNegativeIntegerOnException(): void
    {
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            }
        );

        $this->serializer->method('serialize')->willReturn('{}');

        $httpClient = new MockHttpClient(function (): never {
            throw new \RuntimeException('timeout');
        });

        $repo = $this->makeRepository($billingLogger, $httpClient);

        try {
            $repo->sendBill($this->makeInvoice());
        } catch (\Throwable) {
        }

        self::assertIsInt($capturedContext['duration_ms'], 'duration_ms must be an integer on exception path');
        self::assertGreaterThanOrEqual(0, $capturedContext['duration_ms'], 'duration_ms must be >= 0 on exception path');
    }

    // ── AC-7: unknown tenant_id ───────────────────────────────────────────────

    /**
     * When TenantContext has no tenant set, tenant_id must be the string
     * "unknown" rather than null or a missing key.
     */
    public function testUnknownTenantIdFallsBackToStringUnknown(): void
    {
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            }
        );

        $documentResponse = $this->makeDocumentResponse(success: true);
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        // TenantContext with no tenant set → getTenantId() returns null
        $emptyContext = new TenantContext();

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient, $emptyContext);

        $repo->sendBill($this->makeInvoice());

        self::assertNotNull($capturedContext, 'billingLogger was never called on unknown-tenant path');
        self::assertArrayHasKey('tenant_id', $capturedContext);
        self::assertSame('unknown', $capturedContext['tenant_id']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Returns a LoggerInterface implementation that calls $onLog for every
     * log() / info() / warning() / error() invocation, forwarding the
     * normalised (level, message, context) triple.
     *
     * Using a hand-rolled anonymous class avoids the need for an external
     * TestLogger package while remaining compatible with PHPUnit 12.
     *
     * @param callable(string, string, array<string,mixed>): void $onLog
     */
    private function createSpyLogger(callable $onLog): LoggerInterface
    {
        return new class ($onLog) implements LoggerInterface {
            /** @param callable(string, string, array<string,mixed>): void $onLog */
            public function __construct(private readonly mixed $onLog)
            {
            }

            public function emergency(\Stringable|string $message, array $context = []): void
            {
                ($this->onLog)(LogLevel::EMERGENCY, (string) $message, $context);
            }

            public function alert(\Stringable|string $message, array $context = []): void
            {
                ($this->onLog)(LogLevel::ALERT, (string) $message, $context);
            }

            public function critical(\Stringable|string $message, array $context = []): void
            {
                ($this->onLog)(LogLevel::CRITICAL, (string) $message, $context);
            }

            public function error(\Stringable|string $message, array $context = []): void
            {
                ($this->onLog)(LogLevel::ERROR, (string) $message, $context);
            }

            public function warning(\Stringable|string $message, array $context = []): void
            {
                ($this->onLog)(LogLevel::WARNING, (string) $message, $context);
            }

            public function notice(\Stringable|string $message, array $context = []): void
            {
                ($this->onLog)(LogLevel::NOTICE, (string) $message, $context);
            }

            public function info(\Stringable|string $message, array $context = []): void
            {
                ($this->onLog)(LogLevel::INFO, (string) $message, $context);
            }

            public function debug(\Stringable|string $message, array $context = []): void
            {
                ($this->onLog)(LogLevel::DEBUG, (string) $message, $context);
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                ($this->onLog)((string) $level, (string) $message, $context);
            }
        };
    }
}

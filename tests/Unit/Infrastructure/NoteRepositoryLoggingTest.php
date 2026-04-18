<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\EInvoicing\Billing\Infrastructure\NoteRepository;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\Error;
use Greenter\Model\Sale\Note;
use Greenter\Model\Company\Company;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Asserts structured logging behaviour of NoteRepository::send().
 *
 * Covers SPEC-003 AC-3, AC-6, AC-8:
 *   AC-3  — all 6 context keys present; info on success, warning on SUNAT rejection
 *   AC-6  — exception path: error logged with 6 keys AND exception is re-thrown
 *   AC-8  — duration_ms is a non-negative integer
 */
#[AllowMockObjectsWithoutExpectations]
final class NoteRepositoryLoggingTest extends TestCase
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

    protected function setUp(): void
    {
        $this->serializer = $this->createStub(SerializerInterface::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeRepository(
        LoggerInterface $billingLogger,
        MockHttpClient $httpClient,
    ): NoteRepository {
        return new NoteRepository(
            $this->serializer,
            $httpClient,
            $billingLogger,
            'https://api.example.com/',
            'test-token',
        );
    }

    /**
     * Build a Note with a Company whose RUC acts as tenant_id.
     * The note's getName() is derived from serie + correlativo.
     */
    private function makeNote(
        string $ruc = '20123456789',
        string $serie = 'FC01',
        string $correlativo = '1',
    ): Note {
        $company = new Company();
        $company->setRuc($ruc);

        $note = new Note();
        $note->setCompany($company);
        $note->setSerie($serie);
        $note->setCorrelativo($correlativo);

        return $note;
    }

    /**
     * Build a DocumentResponse whose BillResult reflects the given success flag
     * and optional SUNAT error code/message.
     */
    private function makeDocumentResponse(
        bool $success,
        string $errorCode = '',
        string $errorMessage = '',
    ): DocumentResponse {
        $billResult = (new BillResult())->setSuccess($success);

        if ($errorCode !== '' || $errorMessage !== '') {
            $error = (new Error())
                ->setCode($errorCode)
                ->setMessage($errorMessage);
            $billResult->setError($error);
        }

        $doc = new DocumentResponse();
        $doc->name          = 'FC01-1';
        $doc->xml           = '<xml/>';
        $doc->hash          = 'abc';
        $doc->sunatResponse = $billResult;

        return $doc;
    }

    // ── AC-3: success path ────────────────────────────────────────────────────

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

        $documentResponse = $this->makeDocumentResponse(success: true);
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient);

        $repo->send($this->makeNote());

        self::assertSame(LogLevel::INFO, $capturedLevel, 'Expected info level on SUNAT success');
        self::assertNotNull($capturedContext, 'billingLogger was never called');

        foreach (self::REQUIRED_KEYS as $key) {
            self::assertArrayHasKey($key, $capturedContext, "Missing required context key: {$key}");
        }
    }

    /**
     * On success the context values must match the note's company RUC and name.
     */
    public function testSuccessPathContextValuesAreCorrect(): void
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

        $note = $this->makeNote(ruc: '20999888777', serie: 'FC01', correlativo: '42');

        $repo->send($note);

        self::assertNotNull($capturedContext);
        self::assertTrue($capturedContext['success']);
        self::assertSame('20999888777', $capturedContext['tenant_id']);
        // tax_document_id = getName() = RUC-tipoDoc-serie-correlativo
        self::assertSame($note->getName(), $capturedContext['tax_document_id']);
    }

    // ── AC-3: SUNAT rejection (warning) ──────────────────────────────────────

    /**
     * When SUNAT returns success=false the billingLogger must emit a `warning`
     * entry with all 6 context keys.
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
            errorCode: '2800',
            errorMessage: 'Documento duplicado',
        );
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient);

        $repo->send($this->makeNote());

        self::assertSame(LogLevel::WARNING, $capturedLevel, 'Expected warning level on SUNAT rejection');
        self::assertNotNull($capturedContext);

        foreach (self::REQUIRED_KEYS as $key) {
            self::assertArrayHasKey($key, $capturedContext, "Missing required context key: {$key}");
        }
    }

    /**
     * On SUNAT rejection the context values must reflect the error details.
     */
    public function testSunatRejectionContextValuesAreCorrect(): void
    {
        $capturedContext = null;

        $billingLogger = $this->createSpyLogger(
            function (string $level, string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            }
        );

        $documentResponse = $this->makeDocumentResponse(
            success: false,
            errorCode: '2800',
            errorMessage: 'Documento duplicado',
        );
        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient);

        $note = $this->makeNote(ruc: '20111222333', serie: 'FC01', correlativo: '7');

        $repo->send($note);

        self::assertNotNull($capturedContext);
        self::assertSame('2800', $capturedContext['sunat_code']);
        self::assertSame('Documento duplicado', $capturedContext['sunat_message']);
        self::assertFalse($capturedContext['success']);
        self::assertSame('20111222333', $capturedContext['tenant_id']);
        // tax_document_id = getName() = RUC-tipoDoc-serie-correlativo
        self::assertSame($note->getName(), $capturedContext['tax_document_id']);
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

        $httpClient = new MockHttpClient(function (): never {
            throw new \RuntimeException('Connection timed out');
        });

        $repo = $this->makeRepository($billingLogger, $httpClient);

        try {
            $repo->send($this->makeNote());
        } catch (\Throwable) {
            // expected
        }

        self::assertSame(LogLevel::ERROR, $capturedLevel, 'Expected error level on exception');
        self::assertNotNull($capturedContext, 'billingLogger was never called on exception path');

        foreach (self::REQUIRED_KEYS as $key) {
            self::assertArrayHasKey($key, $capturedContext, "Missing required context key on exception path: {$key}");
        }
    }

    /**
     * On exception success must be false and sunat_code / sunat_message must be
     * present (empty string or "N/A" per spec AC-6).
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
            $repo->send($this->makeNote());
        } catch (\Throwable) {
        }

        self::assertNotNull($capturedContext);
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
            $repo->send($this->makeNote());
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

        $repo->send($this->makeNote());

        self::assertNotNull($capturedContext);
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
            $repo->send($this->makeNote());
        } catch (\Throwable) {
        }

        self::assertNotNull($capturedContext);
        self::assertIsInt($capturedContext['duration_ms'], 'duration_ms must be an integer on exception path');
        self::assertGreaterThanOrEqual(0, $capturedContext['duration_ms'], 'duration_ms must be >= 0 on exception path');
    }

    // ── AC-7: missing company / unknown tenant_id ─────────────────────────────

    /**
     * When the Note's Company has no RUC (getRuc() returns null), tenant_id must
     * fall back to "unknown" rather than null or a missing key (AC-7).
     *
     * Note: NoteRepository derives tenant_id via `$note->getCompany()?->getRuc() ?? 'unknown'`
     * (line 49). A Company with a null RUC exercises this fallback without
     * triggering the null-company crash in BaseSale::getName().
     */
    public function testMissingCompanyFallsBackToStringUnknown(): void
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

        // Company with no RUC → getRuc() returns null → tenant_id falls back to 'unknown'
        $company = new Company();
        // intentionally no setRuc() call

        $note = new Note();
        $note->setCompany($company);
        $note->setSerie('FC01');
        $note->setCorrelativo('1');

        $httpClient = new MockHttpClient(new MockResponse('{}'));
        $repo       = $this->makeRepository($billingLogger, $httpClient);

        $repo->send($note);

        self::assertNotNull($capturedContext);
        self::assertArrayHasKey('tenant_id', $capturedContext);
        self::assertSame('unknown', $capturedContext['tenant_id']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Returns a LoggerInterface implementation that calls $onLog for every
     * log() / info() / warning() / error() invocation, forwarding the
     * normalised (level, message, context) triple.
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

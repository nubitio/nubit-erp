<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Domain;

use App\Billing\Invoice\Domain\InvoiceSendRetryService;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
final class InvoiceSendRetryServiceTest extends TestCase
{
    private Connection&MockObject $connection;
    private LoggerInterface&MockObject $logger;
    private FeatureCheckerInterface&MockObject $featureChecker;
    private InvoiceSendRetryService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->featureChecker = $this->createMock(FeatureCheckerInterface::class);

        $this->featureChecker->method('hasFeature')->willReturn(true);

        $this->service = new InvoiceSendRetryService(
            $this->connection,
            $this->logger,
            $this->featureChecker,
            retryMaxAttempts: 8,
            retryBaseSeconds: 60,
            retryMaxSeconds: 1800,
        );
    }

    // -----------------------------------------------------------------------
    // countCandidateDocuments
    // -----------------------------------------------------------------------

    public function testCountCandidateDocumentsReturnsCastInteger(): void
    {
        $this->connection->method('fetchOne')->willReturn('42');

        self::assertSame(42, $this->service->countCandidateDocuments());
    }

    public function testCountCandidateDocumentsWithFalseResult(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        self::assertSame(0, $this->service->countCandidateDocuments());
    }

    // -----------------------------------------------------------------------
    // fetchCandidateIds
    // -----------------------------------------------------------------------

    public function testFetchCandidateIdsReturnsArrayOfStringIds(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturn(['uuid-1', 'uuid-2', 'uuid-3']);

        $result = $this->service->fetchCandidateIds(10, 0);

        self::assertSame(['uuid-1', 'uuid-2', 'uuid-3'], $result);
    }

    public function testFetchCandidateIdsCastsToString(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturn([1, 2, 3]);

        $result = $this->service->fetchCandidateIds(3, 0);

        self::assertSame(['1', '2', '3'], $result);
    }

    // -----------------------------------------------------------------------
    private function makeServiceWithRetry(int $maxAttempts, int $baseSeconds, int $maxSeconds): InvoiceSendRetryService
    {
        return new InvoiceSendRetryService(
            $this->connection,
            $this->logger,
            $this->featureChecker,
            retryMaxAttempts: $maxAttempts,
            retryBaseSeconds: $baseSeconds,
            retryMaxSeconds: $maxSeconds,
        );
    }

    // -----------------------------------------------------------------------
    // getRetryPolicy
    // -----------------------------------------------------------------------

    public function testGetRetryPolicyHappyPath(): void
    {
        $service = $this->makeServiceWithRetry(5, 30, 900);
        $policy = $service->getRetryPolicy('tenant_test');

        self::assertSame(5, $policy['max_attempts']);
        self::assertSame(30, $policy['base_seconds']);
        self::assertSame(900, $policy['max_seconds']);
    }

    public function testGetRetryPolicyEnforcesMinimumMaxAttempts(): void
    {
        $policy = $this->makeServiceWithRetry(0, 10, 1800)->getRetryPolicy(null);
        self::assertSame(1, $policy['max_attempts']);
    }

    public function testGetRetryPolicyEnforcesMinimumBaseSeconds(): void
    {
        $policy = $this->makeServiceWithRetry(8, 0, 1800)->getRetryPolicy(null);
        self::assertSame(1, $policy['base_seconds']);
    }

    public function testGetRetryPolicyMaxSecondsNotLessThanBaseSeconds(): void
    {
        // max_seconds < base_seconds → max_seconds is clamped to base_seconds
        $policy = $this->makeServiceWithRetry(8, 200, 100)->getRetryPolicy(null);
        self::assertSame(200, $policy['max_seconds']);
    }

    public function testGetRetryPolicyFallbackOnSettingsException(): void
    {
        // Params come from constructor — no exception path possible anymore.
        // Just verify defaults from setUp() are returned.
        $policy = $this->service->getRetryPolicy('tenant_test');
        self::assertSame(['max_attempts' => 8, 'base_seconds' => 60, 'max_seconds' => 1800], $policy);
    }

    // -----------------------------------------------------------------------
    // evaluateRetryEligibility
    // -----------------------------------------------------------------------

    public function testEvaluateEligibilityWithZeroAttempts(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(['attempts' => '0', 'last_attempt_at' => null]);

        $result = $this->service->evaluateRetryEligibility('doc-id', [
            'max_attempts' => 8,
            'base_seconds' => 60,
            'max_seconds' => 1800,
        ]);

        self::assertTrue($result['eligible']);
        self::assertSame('eligible', $result['reason']);
        self::assertSame(0, $result['attempts']);
    }

    public function testEvaluateEligibilityMaxAttemptsReached(): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['attempts' => '8', 'last_attempt_at' => date('Y-m-d H:i:s', time() - 3600)]);

        $result = $this->service->evaluateRetryEligibility('doc-id', [
            'max_attempts' => 8,
            'base_seconds' => 60,
            'max_seconds' => 1800,
        ]);

        self::assertFalse($result['eligible']);
        self::assertSame('max_attempts', $result['reason']);
        self::assertSame(8, $result['attempts']);
    }

    public function testEvaluateEligibilityWithAttemptsButNoLastAttemptAt(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(['attempts' => '3', 'last_attempt_at' => null]);

        $result = $this->service->evaluateRetryEligibility('doc-id', [
            'max_attempts' => 8,
            'base_seconds' => 60,
            'max_seconds' => 1800,
        ]);

        self::assertTrue($result['eligible']);
        self::assertSame('eligible', $result['reason']);
        self::assertSame(3, $result['attempts']);
    }

    public function testEvaluateEligibilityWithinBackoffWindow(): void
    {
        // 1 attempt, base=3600 seconds; last attempt was 60 seconds ago → still in backoff
        $lastAttempt = date('Y-m-d H:i:s', time() - 60);

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['attempts' => '1', 'last_attempt_at' => $lastAttempt]);

        $result = $this->service->evaluateRetryEligibility('doc-id', [
            'max_attempts' => 8,
            'base_seconds' => 3600,
            'max_seconds' => 7200,
        ]);

        self::assertFalse($result['eligible']);
        self::assertSame('backoff', $result['reason']);
        self::assertGreaterThan(0, $result['wait_seconds']);
    }

    public function testEvaluateEligibilityAfterBackoffElapsed(): void
    {
        // 1 attempt, base=60 seconds; last attempt was 120 seconds ago → elapsed
        $lastAttempt = date('Y-m-d H:i:s', time() - 120);

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['attempts' => '1', 'last_attempt_at' => $lastAttempt]);

        $result = $this->service->evaluateRetryEligibility('doc-id', [
            'max_attempts' => 8,
            'base_seconds' => 60,
            'max_seconds' => 1800,
        ]);

        self::assertTrue($result['eligible']);
        self::assertSame('eligible', $result['reason']);
        self::assertSame(0, $result['wait_seconds']);
    }

    // -----------------------------------------------------------------------
    // recordRetrySkipEvent
    // -----------------------------------------------------------------------

    public function testRecordRetrySkipEventBackoffInsertsPendingStatus(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('insert')
            ->with('tax_document_event', self::callback(static function (array $data): bool {
                return $data['status'] === 'pending' && $data['code'] === 'backoff';
            }));

        $this->service->recordRetrySkipEvent('doc-id', 'backoff', 2, 120);
    }

    public function testRecordRetrySkipEventMaxAttemptsInsertsErrorStatus(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('insert')
            ->with('tax_document_event', self::callback(static function (array $data): bool {
                return $data['status'] === 'error' && $data['code'] === 'max_attempts';
            }));

        $this->service->recordRetrySkipEvent('doc-id', 'max_attempts', 8, 0);
    }

    public function testRecordRetrySkipEventEligibleDoesNotInsert(): void
    {
        $this->connection->expects(self::never())->method('insert');

        $this->service->recordRetrySkipEvent('doc-id', 'eligible', 0, 0);
    }

    public function testRecordRetrySkipEventDbFailureLogsWarningAndDoesNotThrow(): void
    {
        $this->connection->method('insert')->willThrowException(new RuntimeException('DB error'));

        $this->logger->expects(self::once())->method('warning');

        // Should not throw
        $this->service->recordRetrySkipEvent('doc-id', 'backoff', 2, 60);
    }

    // -----------------------------------------------------------------------
    // getCurrentStatus
    // -----------------------------------------------------------------------

    public function testGetCurrentStatusReturnsStringStatus(): void
    {
        $this->connection->method('fetchOne')->willReturn('0');

        $result = $this->service->getCurrentStatus('doc-id');

        self::assertSame('0', $result);
    }

    public function testGetCurrentStatusReturnsFalseAsEmptyString(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        $result = $this->service->getCurrentStatus('doc-id');

        self::assertSame('', $result);
    }
}

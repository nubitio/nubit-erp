<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Domain;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class InvoiceSendRetryService
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private FeatureCheckerInterface $featureChecker,
        private int $retryMaxAttempts = 8,
        private int $retryBaseSeconds = 60,
        private int $retryMaxSeconds = 1800,
    ) {}

    public function countCandidateDocuments(): int
    {
        // Fail-closed: if the plan does not include sunat_auto_retry, report
        // zero candidates so the calling command skips the retry cycle entirely.
        // Using hasFeature() (not requireFeature()) because this is invoked from
        // CLI / Scheduler contexts where HTTP 402 exceptions are not meaningful.
        if (!$this->featureChecker->hasFeature('sunat_auto_retry')) {
            return 0;
        }

        return (int) $this->connection->fetchOne("
            SELECT COUNT(1) AS total
            FROM tax_document
            WHERE document_type IN ('01', '03')
              AND issue_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)
              AND (
                sunat_status IS NULL
                OR sunat_status = ''
                OR sunat_status = 'pendiente'
                OR (
                  sunat_status <> '0'
                  AND NOT (
                    sunat_status REGEXP '^[0-9]+$'
                    AND CAST(sunat_status AS UNSIGNED) BETWEEN 2000 AND 3999
                  )
                )
              )
            ");
    }

    /**
     * @return list<string>
     */
    public function fetchCandidateIds(int $limit, int $offset): array
    {
        $ids = $this->connection->fetchFirstColumn(
            "
            SELECT tax_document_id
            FROM tax_document
            WHERE document_type IN ('01', '03')
              AND issue_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)
              AND (
                sunat_status IS NULL
                OR sunat_status = ''
                OR sunat_status = 'pendiente'
                OR (
                  sunat_status <> '0'
                  AND NOT (
                    sunat_status REGEXP '^[0-9]+$'
                    AND CAST(sunat_status AS UNSIGNED) BETWEEN 2000 AND 3999
                  )
                )
              )
            ORDER BY issue_date ASC
            LIMIT :limit OFFSET :offset
            ",
            [
                'limit' => $limit,
                'offset' => $offset,
            ],
            [
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ],
        );

        return array_map(static fn(mixed $id): string => (string) $id, $ids);
    }

    /**
     * @return array{max_attempts:int,base_seconds:int,max_seconds:int}
     */
    public function getRetryPolicy(?string $tenantName): array
    {
        return [
            'max_attempts' => max(1, $this->retryMaxAttempts),
            'base_seconds' => max(1, $this->retryBaseSeconds),
            'max_seconds' => max($this->retryBaseSeconds, $this->retryMaxSeconds),
        ];
    }

    /**
     * @param array{max_attempts:int,base_seconds:int,max_seconds:int} $retryPolicy
     * @return array{eligible:bool,reason:string,attempts:int,wait_seconds:int}
     */
    public function evaluateRetryEligibility(string $taxDocumentId, array $retryPolicy): array
    {
        $row = $this->connection->fetchAssociative("
            SELECT COUNT(1) AS attempts, MAX(created_at) AS last_attempt_at
            FROM tax_document_event
            WHERE tax_document_id = :tax_document_id
              AND event_type = 'sunat_send'
              AND status IN ('error', 'pending')
            ", ['tax_document_id' => $taxDocumentId]);

        $attempts = max(0, (int) (is_array($row) ? $row['attempts'] ?? 0 : 0));
        $lastAttemptAt = is_array($row) && isset($row['last_attempt_at']) ? (string) $row['last_attempt_at'] : '';

        if ($attempts <= 0) {
            return [
                'eligible' => true,
                'reason' => 'eligible',
                'attempts' => 0,
                'wait_seconds' => 0,
            ];
        }

        if ($attempts >= $retryPolicy['max_attempts']) {
            return [
                'eligible' => false,
                'reason' => 'max_attempts',
                'attempts' => $attempts,
                'wait_seconds' => 0,
            ];
        }

        if ($lastAttemptAt === '') {
            return [
                'eligible' => true,
                'reason' => 'eligible',
                'attempts' => $attempts,
                'wait_seconds' => 0,
            ];
        }

        $power = max(0, $attempts - 1);
        $backoff = (int) min($retryPolicy['max_seconds'], $retryPolicy['base_seconds'] * (2 ** $power));
        $lastAttemptTs = strtotime($lastAttemptAt);
        $elapsed = $lastAttemptTs === false ? $backoff : max(0, time() - $lastAttemptTs);

        if ($elapsed >= $backoff) {
            return [
                'eligible' => true,
                'reason' => 'eligible',
                'attempts' => $attempts,
                'wait_seconds' => 0,
            ];
        }

        return [
            'eligible' => false,
            'reason' => 'backoff',
            'attempts' => $attempts,
            'wait_seconds' => $backoff - $elapsed,
        ];
    }

    public function recordRetrySkipEvent(string $taxDocumentId, string $reason, int $attempts, int $waitSeconds): void
    {
        if (!in_array($reason, ['backoff', 'max_attempts'], true)) {
            return;
        }

        $status = $reason === 'max_attempts' ? 'error' : 'pending';
        $message = $reason === 'max_attempts'
            ? sprintf('Retry skipped: max attempts reached (%d).', $attempts)
            : sprintf('Retry skipped by backoff: wait %d seconds.', $waitSeconds);
        $payload = json_encode([
            'reason' => $reason,
            'attempts' => $attempts,
            'wait_seconds' => $waitSeconds,
        ], JSON_UNESCAPED_UNICODE);

        try {
            $this->connection->insert('tax_document_event', [
                'tax_document_id' => $taxDocumentId,
                'event_type' => 'sunat_retry_skip',
                'status' => $status,
                'code' => $reason,
                'message' => $message,
                'payload_json' => $payload,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to persist retry skip event', [
                'tax_document_id' => $taxDocumentId,
                'reason' => $reason,
                'attempts' => $attempts,
                'wait_seconds' => $waitSeconds,
                'exception' => $exception,
            ]);
        }
    }

    public function getCurrentStatus(string $taxDocumentId): string
    {
        return (string) $this->connection->fetchOne('SELECT sunat_status FROM tax_document WHERE tax_document_id = ?', [
            $taxDocumentId,
        ]);
    }
}

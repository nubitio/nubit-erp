<?php

declare(strict_types=1);

namespace App\Catalog\MessageHandler;

use App\Catalog\Message\ProductImportMessage;
use App\Catalog\Service\ProductImportProcessorService;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Processes a product import job asynchronously.
 *
 * Publishes Mercure progress events to the topic:
 *   {tenantOrigin}/api/product-imports/{jobId}
 *
 * Event shapes:
 *  - progress : { jobId, status:"processing", progress:{ current, total } }
 *  - completed: { jobId, status:"completed",  result:{ created, updated, errors } }
 *  - failed   : { jobId, status:"failed",     error: string }
 */
#[AsMessageHandler]
final readonly class ProductImportMessageHandler
{
    /** Minimum rows between two consecutive Mercure progress publishes. */
    private const int PUBLISH_EVERY = 10;

    public function __construct(
        private ProductImportProcessorService $processor,
        private HubInterface $mercureHub,
        private LoggerInterface $logger,
        private TenantContext $tenantContext,
        private TenantConnectionSwitcherInterface $tenantConnectionSwitcher,
    ) {}

    public function __invoke(ProductImportMessage $message): void
    {
        $topic = $this->topic($message);
        $manuallySetTenant = false;

        try {
            $manuallySetTenant = $this->ensureTenantContext($message);

            $this->logger->info('product_import.start', [
                'job_id' => $message->jobId,
                'tenant' => $this->tenantContext->getTenantName(),
            ]);

            $lastPublishedAt = 0;

            $result = $this->processor->process($message->filePath, function (int $current, int $total) use (
                $topic,
                $message,
                &$lastPublishedAt,
            ): void {
                // Throttle publishes: every N rows OR on the last row.
                if (($current - $lastPublishedAt) >= self::PUBLISH_EVERY || $current === $total) {
                    $this->publish($topic, [
                        'jobId' => $message->jobId,
                        'status' => 'processing',
                        'progress' => ['current' => $current, 'total' => $total],
                    ]);
                    $lastPublishedAt = $current;
                }
            });

            $this->publish($topic, [
                'jobId' => $message->jobId,
                'status' => 'completed',
                'result' => $result->toArray(),
            ]);

            $this->logger->info('product_import.done', [
                'job_id' => $message->jobId,
                'created' => $result->created,
                'updated' => $result->updated,
                'errors' => count($result->errors),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('product_import.failed', [
                'job_id' => $message->jobId,
                'exception' => $e->getMessage(),
            ]);

            $this->publish($topic, [
                'jobId' => $message->jobId,
                'status' => 'failed',
                'error' => 'Error procesando el archivo: ' . $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Always clean up the temp file regardless of outcome.
            if (file_exists($message->filePath)) {
                @unlink($message->filePath);
            }

            if ($manuallySetTenant) {
                $this->tenantContext->clear();
            }
        }
    }

    private function ensureTenantContext(ProductImportMessage $message): bool
    {
        if ($message->tenantName === null || $message->tenantName === '') {
            return false;
        }

        if ($this->tenantContext->getTenantName() === $message->tenantName) {
            return false;
        }

        $this->tenantContext->setTenant(
            $message->tenantId,
            $message->tenantName,
            $message->tenantDomain,
            $message->requestId,
        );
        $this->tenantConnectionSwitcher->switchConnection($message->tenantName);

        return true;
    }

    private function topic(ProductImportMessage $message): string
    {
        return rtrim($message->tenantOrigin, '/') . '/api/product-imports/' . $message->jobId;
    }

    /** @param array<string, mixed> $payload */
    private function publish(string $topic, array $payload): void
    {
        try {
            $this->mercureHub->publish(
                new Update($topic, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            );
        } catch (\Throwable $e) {
            $this->logger->warning('product_import.mercure_publish_failed', [
                'topic' => $topic,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

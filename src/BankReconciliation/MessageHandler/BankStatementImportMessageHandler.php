<?php

declare(strict_types=1);

namespace App\BankReconciliation\MessageHandler;

use App\BankReconciliation\Entity\BankStatementImport;
use App\BankReconciliation\Enum\BankImportProfile;
use App\BankReconciliation\Message\BankStatementImportMessage;
use App\BankReconciliation\Service\BankStatementParserService;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Processes a bank statement import job asynchronously.
 *
 * Publishes Mercure progress events to the topic:
 *   {tenantOrigin}/api/bank-statement-imports/{importId}
 *
 * Event shapes mirror App\Catalog\MessageHandler\ProductImportMessageHandler:
 *  - progress : { importId, status:"processing", progress:{ current, total } }
 *  - completed: { importId, status:"completed",  result:{ totalRows, importedRows, errorRows, errors } }
 *  - failed   : { importId, status:"failed",     error: string }
 */
#[AsMessageHandler]
final readonly class BankStatementImportMessageHandler
{
    private const int PUBLISH_EVERY = 10;

    public function __construct(
        private BankStatementParserService $processor,
        private EntityManagerInterface $entityManager,
        private HubInterface $mercureHub,
        private LoggerInterface $logger,
        private TenantContext $tenantContext,
        private TenantConnectionSwitcherInterface $tenantConnectionSwitcher,
    ) {}

    public function __invoke(BankStatementImportMessage $message): void
    {
        $topic = $this->topic($message);
        $manuallySetTenant = false;

        try {
            $manuallySetTenant = $this->ensureTenantContext($message);

            $import = $this->entityManager->find(BankStatementImport::class, $message->importId);
            if (!$import instanceof BankStatementImport) {
                throw new \RuntimeException(sprintf('Import %s no encontrado.', $message->importId));
            }

            $profile = BankImportProfile::from($message->bankProfile);
            $lastPublishedAt = 0;

            $result = $this->processor->process(
                $message->filePath,
                $import->getBankAccount(),
                $import,
                $profile,
                function (int $current, int $total) use ($topic, $message, &$lastPublishedAt): void {
                    if (($current - $lastPublishedAt) >= self::PUBLISH_EVERY || $current === $total) {
                        $this->publish($topic, [
                            'importId' => $message->importId,
                            'status' => 'processing',
                            'progress' => ['current' => $current, 'total' => $total],
                        ]);
                        $lastPublishedAt = $current;
                    }
                },
            );

            $import
                ->setStatus(BankStatementImport::COMPLETED)
                ->setTotalRows($result->totalRows)
                ->setImportedRows($result->importedRows)
                ->setErrorRows($result->getErrorRows())
                ->setErrors($result->errors === [] ? null : $result->errors);
            $this->entityManager->flush();

            $this->publish($topic, [
                'importId' => $message->importId,
                'status' => 'completed',
                'result' => $result->toArray(),
            ]);

            $this->logger->info('bank_statement_import.done', [
                'import_id' => $message->importId,
                'imported_rows' => $result->importedRows,
                'error_rows' => $result->getErrorRows(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('bank_statement_import.failed', [
                'import_id' => $message->importId,
                'exception' => $e->getMessage(),
            ]);

            $import = $this->entityManager->find(BankStatementImport::class, $message->importId);
            if ($import instanceof BankStatementImport) {
                $import->setStatus(BankStatementImport::FAILED);
                $this->entityManager->flush();
            }

            $this->publish($topic, [
                'importId' => $message->importId,
                'status' => 'failed',
                'error' => 'Error procesando el extracto: ' . $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (file_exists($message->filePath)) {
                @unlink($message->filePath);
            }

            if ($manuallySetTenant) {
                $this->tenantContext->clear();
            }
        }
    }

    private function ensureTenantContext(BankStatementImportMessage $message): bool
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

    private function topic(BankStatementImportMessage $message): string
    {
        return rtrim($message->tenantOrigin, '/') . '/api/bank-statement-imports/' . $message->importId;
    }

    /** @param array<string, mixed> $payload */
    private function publish(string $topic, array $payload): void
    {
        try {
            $this->mercureHub->publish(
                new Update($topic, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            );
        } catch (\Throwable $e) {
            $this->logger->warning('bank_statement_import.mercure_publish_failed', [
                'topic' => $topic,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

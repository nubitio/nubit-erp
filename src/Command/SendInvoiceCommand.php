<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Service\TenantNotificationService;
use App\EInvoicing\Invoice\Domain\InvoiceSendOrchestrator;
use App\EInvoicing\Invoice\Domain\InvoiceSendRetryService;
use App\EInvoicing\Invoice\Domain\SunatSendControlService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'invoice:send',
    description: 'Send pending invoices to SUNAT',
)]
#[AsCronTask('*/5 * * * *')]
class SendInvoiceCommand extends TenantAwareCommand
{
    private const int CHUNK_SIZE = 100;

    public function __construct(
        private readonly InvoiceSendOrchestrator $orchestrator,
        private readonly InvoiceSendRetryService $retryService,
        private readonly SunatSendControlService $controlService,
        private readonly TenantNotificationService $notificationService,
        private readonly ManagerRegistry $doctrine,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startedAt = microtime(true);
        $tenantName = $this->currentTenantName;
        $rejectionThreshold = $this->controlService->getRejectionThreshold($tenantName);

        if (!$this->controlService->isSunatSendingEnabled($tenantName)) {
            $io->warning('SUNAT sending is paused by configuration (app_option_sunat_send_enabled=false).');
            $this->logger->warning('Skipping invoice send because SUNAT sending is paused', [
                'tenant' => $tenantName,
                'rejection_threshold' => $rejectionThreshold,
            ]);

            return Command::SUCCESS;
        }

        $retryPolicy = $this->retryService->getRetryPolicy($tenantName);
        $pendingTotal = $this->retryService->countCandidateDocuments();

        if ($pendingTotal === 0) {
            $io->success('No pending invoices to send.');

            return Command::SUCCESS;
        }

        $io->text('SUNAT rejection threshold: ' . $rejectionThreshold);
        $io->text(sprintf(
            'Retry policy: max_attempts=%d, base=%ds, max_backoff=%ds',
            $retryPolicy['max_attempts'],
            $retryPolicy['base_seconds'],
            $retryPolicy['max_seconds'],
        ));
        $io->text('Pending: ' . $pendingTotal);
        $io->progressStart($pendingTotal);

        $errors = [];
        $sent = 0;
        $batchOffset = 0;
        $pausedByRejection = false;
        $skippedByBackoff = 0;
        $skippedByMaxAttempts = 0;

        while (true) {
            $pendingIds = $this->retryService->fetchCandidateIds(self::CHUNK_SIZE, $batchOffset);
            if ($pendingIds === []) {
                break;
            }

            foreach ($pendingIds as $taxDocumentId) {
                $result = $this->orchestrator->process(
                    $taxDocumentId,
                    $retryPolicy,
                    $rejectionThreshold,
                    $tenantName
                );

                if ($result->sent) {
                    $sent++;
                }
                if ($result->error !== null) {
                    $errors[] = [
                        'tax_document_id' => $taxDocumentId,
                        'error' => $result->error,
                    ];
                }
                if ($result->skipReason === 'backoff') {
                    $skippedByBackoff++;
                } elseif ($result->skipReason === 'max_attempts') {
                    $skippedByMaxAttempts++;
                }
                if ($result->paused) {
                    $pausedByRejection = true;
                }

                $this->ensureEntityManagerOpen();
                $this->doctrine->getManager()->clear();
                $io->progressAdvance();

                if ($pausedByRejection) {
                    break;
                }
            }

            if ($pausedByRejection) {
                break;
            }

            $batchOffset += self::CHUNK_SIZE;
        }

        $io->progressFinish();

        if ($errors !== []) {
            $io->section('Errors summary');
            $io->table(['Invoice ID', 'Error'], $errors);
        }

        $durationMs = (int)((microtime(true) - $startedAt) * 1000);
        $io->success(sprintf(
            'Completed. Sent: %d, Failed: %d, Duration: %d ms',
            $sent,
            count($errors),
            $durationMs
        ));

        $this->logger->info('Invoice send command completed', [
            'pending_total' => $pendingTotal,
            'sent' => $sent,
            'failed' => count($errors),
            'duration_ms' => $durationMs,
            'paused_by_rejection' => $pausedByRejection,
            'rejection_threshold' => $rejectionThreshold,
            'retry_max_attempts' => $retryPolicy['max_attempts'],
            'retry_base_seconds' => $retryPolicy['base_seconds'],
            'retry_max_seconds' => $retryPolicy['max_seconds'],
            'skipped_by_backoff' => $skippedByBackoff,
            'skipped_by_max_attempts' => $skippedByMaxAttempts,
        ]);

        if ($skippedByMaxAttempts > 0 && is_string($tenantName) && $tenantName !== '') {
            $subject = sprintf('[SUNAT] Documentos bloqueados por max attempts - %s', $tenantName);
            $message = sprintf(
                'Se detectaron %d comprobantes bloqueados por max attempts en esta corrida.',
                $skippedByMaxAttempts
            );
            $this->notificationService->dispatchForTenantName(
                tenantName: $tenantName,
                eventKey: TenantNotificationService::EVENT_SUNAT_MAX_ATTEMPTS,
                subject: $subject,
                message: $message,
                payload: [
                    'tenant_name' => $tenantName,
                    'skipped_by_max_attempts' => $skippedByMaxAttempts,
                    'occurred_at' => date(DATE_ATOM),
                ],
                severity: 'warning',
            );
        }

        return $errors === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function ensureEntityManagerOpen(): void
    {
        $manager = $this->doctrine->getManager();
        if ($manager instanceof EntityManagerInterface && !$manager->isOpen()) {
            $this->doctrine->resetManager();
        }
    }
}

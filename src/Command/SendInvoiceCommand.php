<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Service\TenantConnectionSwitcher;
use App\Core\Service\TenantManager;
use App\EInvoicing\Invoice\Domain\InvoiceSendUseCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Throwable;

#[AsCommand(
    name: 'invoice:send',
    description: 'Send pending invoices to SUNAT',
)]
#[AsCronTask('*/5 * * * *')]
class SendInvoiceCommand extends TenantAwareCommand
{
    private const int CHUNK_SIZE = 100;

    public function __construct(
        TenantManager $tenantManager,
        TenantConnectionSwitcher $tenantConnectionSwitcher,
        LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly InvoiceSendUseCase $invoiceSendUseCase,
    ) {
        parent::__construct($tenantManager, $tenantConnectionSwitcher, $logger);
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

        $pendingTotal = (int)$this->connection->fetchOne(
            "
            SELECT COUNT(1) AS total
            FROM tax_document
            WHERE document_type IN ('01', '03')
              AND (sunat_status IS NULL OR sunat_status = '' OR sunat_status = 'pendiente')
              AND issue_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            "
        );

        if ($pendingTotal === 0) {
            $io->success('No pending invoices to send.');

            return Command::SUCCESS;
        }

        $io->text('Pending: ' . $pendingTotal);
        $io->progressStart($pendingTotal);

        $errors = [];
        $sent = 0;
        $batchOffset = 0;

        while (true) {
            $pendingIds = $this->connection->fetchFirstColumn(
                "
                SELECT tax_document_id
                FROM tax_document
                WHERE document_type IN ('01', '03')
                  AND (sunat_status IS NULL OR sunat_status = '' OR sunat_status = 'pendiente')
                  AND issue_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                ORDER BY issue_date ASC
                LIMIT :limit OFFSET :offset
                ",
                [
                    'limit' => self::CHUNK_SIZE,
                    'offset' => $batchOffset,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                    'offset' => ParameterType::INTEGER,
                ]
            );

            if ($pendingIds === []) {
                break;
            }

            foreach ($pendingIds as $taxDocumentId) {
                try {
                    $response = $this->invoiceSendUseCase->__invoke((string)$taxDocumentId);

                    if ($response->sunatResponse->isSuccess()) {
                        $sent++;
                    } else {
                        $message = $response->sunatResponse->getError()?->getMessage() ?? 'Unknown error';
                        $errors[] = [
                            'movimiento_id' => (string)$taxDocumentId,
                            'error' => $message,
                        ];
                        $this->logger->warning('Invoice sending warning', [
                            'movimiento_id' => $taxDocumentId,
                            'message' => $message,
                        ]);
                    }
                } catch (Throwable $exception) {
                    $errors[] = [
                        'movimiento_id' => (string)$taxDocumentId,
                        'error' => $exception->getMessage(),
                    ];
                    $this->logger->error('Error sending invoice', [
                        'movimiento_id' => $taxDocumentId,
                        'exception' => $exception,
                    ]);
                } finally {
                    $io->progressAdvance();
                }
            }

            $batchOffset += self::CHUNK_SIZE;
        }

        $io->progressFinish();

        if ($errors !== []) {
            $io->section('Errors summary');
            $io->table(['Invoice ID', 'Error'], $errors);
        }

        $durationMs = (int)((microtime(true) - $startedAt) * 1000);

        $io->success(
            sprintf(
                'Completed. Sent: %d, Failed: %d, Duration: %d ms',
                $sent,
                count($errors),
                $durationMs
            )
        );

        $this->logger->info('Invoice send command completed', [
            'pending_total' => $pendingTotal,
            'sent' => $sent,
            'failed' => count($errors),
            'duration_ms' => $durationMs,
        ]);

        if ($errors !== []) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

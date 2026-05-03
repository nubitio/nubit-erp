<?php

declare(strict_types=1);

namespace App\Billing\Command;

use Nubit\Platform\Symfony\Console\PerTenantCommand;
use App\Efact\Service\SettingsService;
use App\Settings\Entity\ConfigKey;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'invoice:retry:reset',
    description: 'Reset retry state for one invoice so it can be resent',
)]
class ResetInvoiceRetryCommand extends PerTenantCommand
{
    public function __construct(
        private readonly \Doctrine\DBAL\Connection $connection,
        private readonly SettingsService $settings,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('tax_document_id', InputArgument::REQUIRED, 'Tax document UUID');
        $this->addOption(
            'allow-rejected',
            null,
            InputOption::VALUE_NONE,
            'Allow resetting documents rejected by SUNAT (2xxx-3xxx)'
        );
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $taxDocumentId = trim((string)$input->getArgument('tax_document_id'));
        $allowRejected = (bool)$input->getOption('allow-rejected');

        $row = $this->connection->fetchAssociative(
            "
            SELECT tax_document_id, document_type, sunat_status
            FROM tax_document
            WHERE tax_document_id = :tax_document_id
              AND document_type IN ('01', '03')
            LIMIT 1
            ",
            ['tax_document_id' => $taxDocumentId]
        );

        if (!is_array($row)) {
            $io->error('Tax document not found for invoice/receipt types (01/03).');

            return Command::FAILURE;
        }

        $sunatStatus = (string)($row['sunat_status'] ?? '');
        if (!$allowRejected && $this->isRejectedStatus($sunatStatus)) {
            $io->error(
                sprintf(
                    'Document has SUNAT rejected status (%s). Use --allow-rejected if you really want to reset it.',
                    $sunatStatus
                )
            );

            return Command::FAILURE;
        }

        $updatedEvents = 0;
        $this->connection->beginTransaction();

        try {
            $updatedEvents = $this->connection->executeStatement(
                "
                UPDATE tax_document_event
                SET status = 'reset'
                WHERE tax_document_id = :tax_document_id
                  AND event_type = 'sunat_send'
                  AND status IN ('error', 'pending')
                ",
                ['tax_document_id' => $taxDocumentId]
            );

            $this->connection->executeStatement(
                "
                UPDATE tax_document
                SET sunat_status = 'pendiente',
                    sunat_message = :message,
                    updated_at = NOW()
                WHERE tax_document_id = :tax_document_id
                  AND (sunat_status IS NULL OR sunat_status <> '0')
                ",
                [
                    'tax_document_id' => $taxDocumentId,
                    'message' => 'Manual retry reset',
                ]
            );

            $payload = json_encode([
                'updated_events' => $updatedEvents,
                'allow_rejected' => $allowRejected,
            ], JSON_UNESCAPED_UNICODE);

            $this->connection->insert('tax_document_event', [
                'tax_document_id' => $taxDocumentId,
                'event_type' => 'sunat_retry_reset',
                'status' => 'pending',
                'code' => null,
                'message' => 'Retry state reset manually',
                'payload_json' => $payload,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->settings->set(ConfigKey::SUNAT_REJECTION_STREAK->value, 'int', '0');

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            $this->logger->error('Failed to reset invoice retry state', [
                'tax_document_id' => $taxDocumentId,
                'tenant' => $this->currentTenantName,
                'exception' => $exception,
            ]);
            $io->error('Failed to reset retry state: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Retry state reset for %s. Previous pending/error send events updated: %d',
            $taxDocumentId,
            $updatedEvents
        ));

        return Command::SUCCESS;
    }

    private function isRejectedStatus(string $status): bool
    {
        if ($status === '' || !ctype_digit($status)) {
            return false;
        }

        $numeric = (int)$status;

        return $numeric >= 2000 && $numeric < 4000;
    }
}

<?php

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use App\Core\Infrastructure\Service\TenantManager;
use App\Invoice\Domain\InvoiceSendUseCase;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'invoice:send',
    description: 'Send invoice',
)]
#[AsCronTask(expression: '0 * * * *')]
class SendInvoiceCommand extends TenantAwareCommand
{
    public function __construct(
        TenantManager $tenantManager,
        TenantConnectionSwitcher $tenantConnectionSwitcher,
        LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly InvoiceSendUseCase $invoiceSendUseCase,
    ) {
        parent::__construct($tenantManager, $tenantConnectionSwitcher, $logger);
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Send pending invoices to SUNAT');
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pending = $this->connection->fetchAllAssociative(
            "
            SELECT
                movimiento_id
            FROM comprobante
            WHERE
                (envio_sunat REGEXP '[^0-9]' OR envio_sunat IS NULL)
                AND fecha >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            "
        );

        $io->text('Pending: ' . count($pending));

        $errors = [];

        /** @var array<string, string> $item */
        foreach ($pending as $item) {
            try {
                $response = $this->invoiceSendUseCase->__invoke($item['movimiento_id']);

                if ($response->sunatResponse->isSuccess()) {
                    $io->success($response->sunatResponse->getCdrResponse()?->getDescription() ?? '');
                } else {
                    $message = $response->sunatResponse->getError()?->getMessage() ?? 'Unknown error';
                    $io->warning($message);
                    $this->logger->warning('Invoice sending warning', [
                        'movimiento_id' => $item['movimiento_id'],
                        'message' => $message
                    ]);
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'movimiento_id' => $item['movimiento_id'],
                    'error' => $e->getMessage()
                ];
                $this->logger->error('Error sending invoice', [
                    'movimiento_id' => $item['movimiento_id'],
                    'exception' => $e
                ]);
                $io->error('Error processing invoice ' . $item['movimiento_id'] . ': ' . $e->getMessage());
            }
        }

        if (!empty($errors)) {
            $io->section('Errors summary');
            foreach ($errors as $error) {
                $io->text('Invoice ID: ' . $error['movimiento_id'] . ' - Error: ' . $error['error']);
            }
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Invoice\Application;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use App\Core\Infrastructure\Service\TenantManager;
use App\Invoice\Domain\InvoiceSendUseCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'invoice:send',
    description: 'Send invoice',
)]
class SendInvoiceCommand extends Command
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        private readonly Connection $connection,
        private readonly InvoiceSendUseCase $invoiceSendUseCase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Envía comprobante');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $tenants = $this->tenantManager->getTenants();

            foreach ($tenants as $tenant) {
                /** @var string $tenantName */
                $tenantName = $tenant['name'];
                $this->tenantConnectionSwitcher->switchConnection($tenantName);
                $io->title('Tenant: '.$tenantName);

                $this->sendInvoice($io);
            }

            $io->success('Done!');

            return Command::SUCCESS;
        } catch (ServiceException|Exception|FilesystemException|InvalidArgumentException $e) {
            $io->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws FilesystemException
     * @throws ServiceException
     * @throws Exception
     */
    private function sendInvoice(SymfonyStyle $io): void
    {
        $pending = $this->connection->fetchAllAssociative("
            SELECT
                movimiento_id
            FROM comprobante
            WHERE
                (envio_sunat REGEXP '[^0-9]' OR envio_sunat IS NULL)
                AND fecha >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            ");

        $io->text('Pending: '.count($pending));

        /** @var array<string, string> $item */
        foreach ($pending as $item) {
            $response = $this->invoiceSendUseCase->__invoke($item['movimiento_id']);

            if ($response->sunatResponse->isSuccess()) {
                $io->success($response->sunatResponse->getCdrResponse()?->getDescription() ?? '');
            } else {
                $io->warning($response->sunatResponse->getError()?->getMessage() ?? '');
            }
        }
    }
}

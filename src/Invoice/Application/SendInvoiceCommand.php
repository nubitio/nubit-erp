<?php

namespace App\Invoice\Application;

use App\Invoice\Domain\InvoiceSendUseCase;
use Doctrine\DBAL\Connection;
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
            $pending = $this->connection->fetchAllAssociative("SELECT movimiento_id FROM comprobante WHERE (envio_sunat REGEXP '[^0-9]' OR envio_sunat IS NULL)");

            /** @var array<string, string> $item */
            foreach ($pending as $item) {
                $response = $this->invoiceSendUseCase->__invoke($item['movimiento_id']);

                if ($response->sunatResponse->success) {
                    $io->success($response->sunatResponse->cdrResponse->description);
                } else {
                    $io->error($response->sunatResponse->error->message);
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        } catch (FilesystemException|InvalidArgumentException $e) {
            $io->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}

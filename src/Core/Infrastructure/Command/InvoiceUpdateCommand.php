<?php

namespace App\Core\Infrastructure\Command;

use App\Core\Infrastructure\Service\InvoiceUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'invoice:update',
    description: 'Invoice update values',
)]
class InvoiceUpdateCommand extends Command
{
    public function __construct(private readonly InvoiceUpdater $invoiceUpdater)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setHelp('Actualiza valores de los comprobantes');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Actualizando valores de los comprobantes');
            $io->text('Esto puede tardar unos minutos');
            $result = $this->invoiceUpdater->update();
            $io->text($result);
            $io->success('Success');

            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $io->error('Error: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}

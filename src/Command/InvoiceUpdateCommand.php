<?php

namespace App\Command;

use App\Infrastructure\Service\InvoiceUpdater;
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
    private InvoiceUpdater $invoiceUpdater;

    public function __construct(InvoiceUpdater $invoiceUpdater)
    {
        parent::__construct();
        $this->invoiceUpdater = $invoiceUpdater;
    }

    protected function configure()
    {
        $this->setHelp('Actualiza valores de los comprobantes');
    }

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
        } catch (\Exception $e) {
            $io->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}

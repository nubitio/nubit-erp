<?php

namespace App\Core\Infrastructure\Command;

use App\Core\Infrastructure\Service\SalesUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sale:update',
    description: 'Sale update command'
)]
class SaleUpdateCommand extends Command
{
    private SalesUpdater $salesUpdater;

    public function __construct(SalesUpdater $salesUpdater)
    {
        parent::__construct();
        $this->salesUpdater = $salesUpdater;
    }

    protected function configure(): void
    {
        $this->setHelp('Actualiza valores de los comprobantes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Actualizando ventas');
            $io->text('Esto puede tardar unos minutos');
            $result = $this->salesUpdater->update();
            $io->text($result);
            $io->success('Success');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}

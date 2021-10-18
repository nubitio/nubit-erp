<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemoCommand extends Command
{
    protected static $defaultName = 'app:run-demo';

    protected function configure()
    {
        $this->setHelp('Help text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Demo Title');
            $io->text('Demo text');
            $io->success('Success');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error');
            return Command::FAILURE;
        }
    }
}
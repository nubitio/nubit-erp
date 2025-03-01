<?php

namespace App\Core\Infrastructure\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notify:sms',
    description: 'Send sms',
)]
class SendSmsCommand extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this->setHelp('Send a sms to a user');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Demo Title');
            $io->text('Demo text');
            $io->success('Success');

            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $io->error('Error: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}

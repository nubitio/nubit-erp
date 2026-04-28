<?php

declare(strict_types=1);

namespace App\Identity\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'notify:sms', description: 'Send sms', help: <<<'TXT'
Send a sms to a user
TXT)]
class SendSmsCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $io->title('Demo Title');
            $io->text('Demo text');
            $io->success('Success');

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $io->error('Error: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}

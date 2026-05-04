<?php

declare(strict_types=1);

namespace App\AI\Command;

use App\AI\Knowledge\KnowledgeIndex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:ai:build-knowledge',
    description: 'Rebuild the local knowledge base index from docs/.',
)]
#[AsCronTask('0 0 * * *')]
final class BuildKnowledgeIndexCommand extends Command
{
    public function __construct(
        private readonly KnowledgeIndex $index,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $input->getArguments();
        $this->index->build();
        $output->writeln('<info>Knowledge index rebuilt successfully.</info>');

        return Command::SUCCESS;
    }
}

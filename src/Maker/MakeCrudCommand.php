<?php

namespace App\Maker;

use App\Core\Domain\Util\StringUtils;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:app:crud',
    description: 'Creates a new App CRUD',
)]
class MakeCrudCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this->setHelp('Crud generator for a given entity');
        $this->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactive mode');
        $this->addOption('overwrite', 'o', InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isInteractive = boolval($input->getOption('interactive') ?? false);
        $overwrite = boolval($input->getOption('overwrite') ?? false);
        $io = new SymfonyStyle($input, $output);
        try {
            $io->text('Please select the crud type');
            $crudType = $io->choice('Crud type', ['Simple', 'Master detail']);
            $io->newLine();

            $moduleName = strval($io->ask('Module name'));
            $modulePath = dirname(__DIR__, 2) . '/src/' . StringUtils::toPascalCase($moduleName);

            $schemaManager = $this->connection->createSchemaManager();
            $tables = $schemaManager->listTableNames();
            if (empty($tables)) {
                $io->error('No tables found in the database');

                return Command::FAILURE;
            }

            if ($crudType === 'Master detail') {
                $crudMaker = new MasterDetailCrudMaker($io, $schemaManager, $moduleName, $modulePath, $isInteractive, $overwrite);
            } else {
                $crudMaker = new SimpleCrudMaker($io, $schemaManager, $moduleName, $modulePath, $isInteractive, $overwrite);
            }

            $crudMaker->makeCrud();
            $io->success('Success');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            $io->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}

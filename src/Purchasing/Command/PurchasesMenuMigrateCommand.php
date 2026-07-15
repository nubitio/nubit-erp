<?php

declare(strict_types=1);

namespace App\Purchasing\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purchases:menu:migrate',
    description: 'Migrate legacy inbound menu entries to purchases route for existing tenants.',
)]
final class PurchasesMenuMigrateCommand extends PerTenantCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report rows that would be updated.');
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $count = (int) $this->entityManager
            ->createQuery('SELECT COUNT(m.id) FROM App\\Settings\\Entity\\Menu m WHERE m.route = :route')
            ->setParameter('route', 'inbound')
            ->getSingleScalarResult();

        if ($dryRun) {
            $io->note(sprintf('Rows to update: %d', $count));

            return Command::SUCCESS;
        }

        if ($count === 0) {
            $io->note('No legacy inbound menu rows found.');

            return Command::SUCCESS;
        }

        $updated = (int) $this->entityManager
            ->createQuery(<<<'DQL'
                UPDATE App\Settings\Entity\Menu m
                SET m.route = :targetRoute,
                    m.keyName = CASE WHEN m.keyName = :legacyName THEN :targetName ELSE m.keyName END
                WHERE m.route = :legacyRoute
                DQL)
            ->setParameter('targetRoute', 'purchases')
            ->setParameter('legacyName', 'Ingresos')
            ->setParameter('targetName', 'Compras')
            ->setParameter('legacyRoute', 'inbound')
            ->execute();

        $io->success(sprintf('Menu migration completed (updated=%d).', $updated));

        return Command::SUCCESS;
    }
}

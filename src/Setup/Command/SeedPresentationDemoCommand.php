<?php

declare(strict_types=1);

namespace App\Setup\Command;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\RoleRepository;
use App\Identity\Repository\UserRepository;
use App\Setup\InitialSetupService;
use App\Setup\Service\PresentationDemoSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ConnectionRegistry;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates a small, credible mini-ERP dataset for a sales presentation.
 *
 * This deliberately refuses to mix synthetic data into an active tenant.
 * Run it once on a fresh demo tenant after initial setup.
 */
#[AsCommand(
    name: 'app:demo:seed',
    description: 'Prepare a fresh tenant with a commercial presentation account and mini-ERP sample data.',
)]
final class SeedPresentationDemoCommand extends PerTenantCommand
{
    private const string USERNAME = 'demo@empresa.pe';
    private const string DEFAULT_PASSWORD = 'Demo.2026!';

    public function __construct(
        private readonly InitialSetupService $initialSetupService,
        private readonly PresentationDemoSeeder $presentationDemoSeeder,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'password',
            null,
            InputOption::VALUE_OPTIONAL,
            'Password for demo@empresa.pe',
            self::DEFAULT_PASSWORD,
        )->setHelp(<<<'HELP'
            Creates an owner account and a concise mini-ERP dataset for a product demonstration:
            catalog, sales, purchases, receivables/payables, quotes, payroll, banks, assets and ledger.

            The command is idempotent: once a tenant has products or sales it stops without changing
            operational data. Use it only on a fresh, dedicated presentation tenant.

            Example:
              php bin/console app:demo:seed --tenant=demo --no-interaction

            Login after seeding:
              usuario: demo@empresa.pe
              contraseña: Demo.2026! (or the value passed with --password)
            HELP);
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->initialSetupService->status()->configured) {
            $io->error(
                'Primero complete el setup inicial del tenant. El seed de presentación no configura empresas reales.',
            );

            return Command::FAILURE;
        }

        $connection = $this->connectionRegistry->getConnection();
        $productCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM producto');
        $saleCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM sales');

        if ($productCount > 0 || $saleCount > 0) {
            $io->warning(sprintf(
                'El tenant ya contiene %d productos y %d ventas. No se mezclarán datos de presentación.',
                $productCount,
                $saleCount,
            ));
            $io->note('Cree un tenant demo nuevo para una presentación limpia.');

            return Command::FAILURE;
        }

        $password = (string) $input->getOption('password');
        if (mb_strlen($password) < 8) {
            $io->error('La contraseña de demo debe tener al menos 8 caracteres.');

            return Command::FAILURE;
        }

        $demoUser = $this->upsertDemoAdministrator($password);
        $this->initialSetupService->ensureDefaultVoucherSeriesForAllBranches();

        $io->section('Creando una historia comercial de mini ERP…');
        $stats = $this->presentationDemoSeeder->seed($demoUser);

        $io->success('Tenant listo para presentación comercial de mini ERP.');
        $io->table(['Incluye', 'Cantidad'], [
            ['Clientes', $stats['customers']],
            ['Productos', $stats['products']],
            ['Proveedores', $stats['suppliers']],
            ['Ventas (hoy + histórico 12 meses)', $stats['sales']],
            ['Compras y cuentas por pagar', $stats['purchases']],
            ['Empleados y planilla', $stats['employees']],
        ]);
        $io->writeln(sprintf('<info>Acceso: %s / %s</info>', self::USERNAME, $password));
        $io->note(
            'También incluye cotizaciones, bancos, activos fijos, asientos contables y caja abierta para el POS.',
        );

        return Command::SUCCESS;
    }

    private function upsertDemoAdministrator(#[\SensitiveParameter] string $password): User
    {
        // The presentation account must open every commercial module without
        // depending on a tenant's custom administrator grants.
        $role = $this->roleRepository->find(1);
        if (!$role instanceof Role) {
            throw new \RuntimeException(
                'No existe el rol Super Administrador. Ejecute el setup inicial antes del seed.',
            );
        }

        $user = $this->userRepository->findOneBy(['username' => self::USERNAME]);
        if (!$user instanceof User) {
            $user = new User()->setUsername(self::USERNAME);
            $this->entityManager->persist($user);
        }

        $user->setRole($role)->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->flush();

        return $user;
    }
}

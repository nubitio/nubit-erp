<?php

declare(strict_types=1);

namespace App\Setup\Command;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\RoleRepository;
use App\Identity\Repository\UserRepository;
use App\Setup\Demo\Minimarket\MinimarketDemoSeeder;
use App\Setup\Demo\Minimarket\MinimarketPlan;
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
    private const string STORY_TRADER = 'comercializadora';
    private const string STORY_MINIMARKET = 'minimarket';

    public function __construct(
        private readonly InitialSetupService $initialSetupService,
        private readonly PresentationDemoSeeder $presentationDemoSeeder,
        private readonly MinimarketDemoSeeder $minimarketDemoSeeder,
        private readonly MinimarketPlan $minimarketPlan,
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
        $this
            ->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                'Password for demo@empresa.pe',
                self::DEFAULT_PASSWORD,
            )
            ->addOption(
                'story',
                null,
                InputOption::VALUE_REQUIRED,
                'Story to tell: "minimarket" (a neighbourhood shop, two cashiers) or "comercializadora" (the original mini-ERP)',
                self::STORY_TRADER,
            )
            ->setHelp(<<<'HELP'
                Creates an owner account and a concise mini-ERP dataset for a product demonstration:
                catalog, sales, purchases, receivables/payables, quotes, payroll, banks, assets and ledger.

                The command is idempotent: once a tenant has products or sales it stops without changing
                operational data. Use it only on a fresh, dedicated presentation tenant.

                Example:
                  php bin/console app:demo:seed --tenant=demo --no-interaction
                  php bin/console app:demo:seed --tenant=demo --story=minimarket --no-interaction

                The minimarket profile tells the story of a neighbourhood shop: two cashiers (maria.cajera,
                luis.cajero, same password) and 45 days of sales, with the signals the "Control de caja" view
                is built to show. Nothing in it is random: the story is the same on every run.

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

        /** @var array<string, string> $existingDataCounts */
        $existingDataCounts = [
            'productos' => 'SELECT COUNT(*) FROM producto',
            'ventas' => 'SELECT COUNT(*) FROM sales',
            'compras' => 'SELECT COUNT(*) FROM goods_receipts',
            'planilla' => 'SELECT COUNT(*) FROM payroll_run',
            'asientos contables' => 'SELECT COUNT(*) FROM journal_entry',
        ];

        $foundExistingData = [];
        foreach ($existingDataCounts as $label => $query) {
            $count = (int) $connection->fetchOne($query);
            if ($count > 0) {
                $foundExistingData[$label] = $count;
            }
        }

        if ($foundExistingData !== []) {
            $summary = implode(', ', array_map(
                // "label: N" instead of "N label" sidesteps Spanish singular/plural
                // agreement (e.g. "1 productos" would be wrong) without a pluralizer.
                static fn(string $label, int $count): string => sprintf('%s: %d', $label, $count),
                array_keys($foundExistingData),
                array_values($foundExistingData),
            ));
            $io->warning(sprintf(
                'El tenant ya contiene datos operativos (%s). No se mezclarán datos de presentación.',
                $summary,
            ));
            $io->note('Cree un tenant demo nuevo para una presentación limpia.');

            return Command::FAILURE;
        }

        $story = (string) $input->getOption('story');
        if (!in_array($story, [self::STORY_TRADER, self::STORY_MINIMARKET], true)) {
            $io->error(sprintf(
                'Historia desconocida «%s». Use «%s» o «%s».',
                $story,
                self::STORY_MINIMARKET,
                self::STORY_TRADER,
            ));

            return Command::FAILURE;
        }

        $password = (string) $input->getOption('password');
        if (mb_strlen($password) < 8) {
            $io->error('La contraseña de demo debe tener al menos 8 caracteres.');

            return Command::FAILURE;
        }

        $demoUser = $this->upsertDemoAdministrator($password);
        $this->initialSetupService->ensureDefaultVoucherSeriesForAllBranches();

        if ($story === self::STORY_MINIMARKET) {
            return $this->seedMinimarket($io, $demoUser, $password, (string) $input->getOption('tenant'));
        }

        $io->section('Creando una historia comercial de mini ERP…');
        $stats = $this->presentationDemoSeeder->seed($demoUser);

        $io->success('Tenant listo para presentación comercial de mini ERP.');
        $io->table(['Incluye', 'Cantidad'], [
            ['Clientes', $stats['customers']],
            ['Productos', $stats['products']],
            ['Suscripciones de hosting', $stats['subscriptions']],
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

    private function seedMinimarket(
        SymfonyStyle $io,
        User $owner,
        #[\SensitiveParameter]
        string $password,
        string $tenant,
    ): int {
        if ($this->minimarketPlan->ensure($tenant)) {
            $io->note(sprintf(
                'El tenant pasó al plan «%s»: es el que muestra la configuración y el inventario completos.',
                MinimarketPlan::PLAN,
            ));
        }

        $io->section('Abriendo el minimarket…');
        $stats = $this->minimarketDemoSeeder->seed($owner, $password);

        $io->success('Tenant listo: minimarket de barrio con 45 días de historia.');
        $io->table(['Incluye', 'Cantidad'], [
            ['Productos', $stats['products']],
            ['Clientes con cuenta o factura', $stats['customers']],
            ['Proveedores', $stats['suppliers']],
            ['Turnos de caja', $stats['shifts']],
            ['Ventas registradas', $stats['sales']],
            ['  de ellas anuladas', $stats['voided']],
            ['Notas de crédito', $stats['creditNotes']],
            ['Compras de reposición', $stats['purchases']],
        ]);
        $io->writeln(sprintf('<info>Dueña/o: %s / %s</info>', self::USERNAME, $password));
        $io->writeln(sprintf('<info>Cajeros: maria.cajera y luis.cajero / %s</info>', $password));

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

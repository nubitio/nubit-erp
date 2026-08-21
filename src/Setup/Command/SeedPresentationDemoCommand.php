<?php

declare(strict_types=1);

namespace App\Setup\Command;

use App\CashRegister\Entity\CashSession;
use App\CashRegister\Repository\CashRegisterRepository;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\RoleRepository;
use App\Identity\Repository\UserRepository;
use App\Inventory\Repository\WarehouseRepository;
use App\Sales\Repository\DashboardRepository;
use App\Setup\InitialSetupService;
use App\Setup\Service\TestDataSeeder;
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
 * Creates a small, credible retail dataset for a sales presentation.
 *
 * This deliberately refuses to mix synthetic data into an active tenant.
 * Run it once on a fresh demo tenant after initial setup.
 */
#[AsCommand(
    name: 'app:demo:seed',
    description: 'Prepare a fresh tenant with a commercial presentation account and representative retail data.',
)]
final class SeedPresentationDemoCommand extends PerTenantCommand
{
    private const string USERNAME = 'demo@empresa.pe';
    private const string DEFAULT_PASSWORD = 'Demo.2026!';

    public function __construct(
        private readonly InitialSetupService $initialSetupService,
        private readonly TestDataSeeder $testDataSeeder,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly WarehouseRepository $warehouseRepository,
        private readonly CashRegisterRepository $cashRegisterRepository,
        private readonly DashboardRepository $dashboardRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Password for demo@empresa.pe', self::DEFAULT_PASSWORD)
            ->setHelp(<<<'HELP'
                Creates an owner account and a concise retail dataset for a product demonstration.

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
            $io->error('Primero complete el setup inicial del tenant. El seed de presentación no configura empresas reales.');

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

        $io->section('Creando una historia comercial de venta, catálogo e inventario…');
        $stats = $this->testDataSeeder->seed(
            customerCount: 24,
            productCount: 48,
            supplierCount: 8,
            saleCount: 36,
            batchSize: 24,
        );
        $this->hydratePresentationReadModels();
        $this->openPresentationCashSession($demoUser);

        $io->success('Tenant listo para presentación comercial.');
        $io->table(['Incluye', 'Cantidad nueva'], [
            ['Clientes', $stats['customers_created']],
            ['Productos', $stats['products_created']],
            ['Proveedores', $stats['suppliers_created']],
            ['Ventas históricas', $stats['sales_created']],
        ]);
        $io->writeln(sprintf('<info>Acceso: %s / %s</info>', self::USERNAME, $password));

        return Command::SUCCESS;
    }

    private function upsertDemoAdministrator(string $password): User
    {
        // The presentation account must open every commercial module without
        // depending on a tenant's custom administrator grants.
        $role = $this->roleRepository->find(1);
        if (!$role instanceof Role) {
            throw new \RuntimeException('No existe el rol Super Administrador. Ejecute el setup inicial antes del seed.');
        }

        $user = $this->userRepository->findOneBy(['username' => self::USERNAME]);
        if (!$user instanceof User) {
            $user = (new User())->setUsername(self::USERNAME);
            $this->entityManager->persist($user);
        }

        $user
            ->setRole($role)
            ->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->flush();

        return $user;
    }

    /** Build the dashboard's denormalized views from the synthetic sales. */
    private function hydratePresentationReadModels(): void
    {
        $paymentMethodId = $this->connectionRegistry->getConnection()->fetchOne('SELECT id FROM payment_method ORDER BY id LIMIT 1');
        if ($paymentMethodId !== false && $paymentMethodId !== null) {
            $this->connectionRegistry->getConnection()->executeStatement(
                <<<'SQL'
                    INSERT INTO sale_payments
                        (sale_id, payment_method_id, amount, currency, paid_at, created_at, sync_status, version_no, last_modified_at)
                    SELECT s.id, :payment_method_id, s.grand_total, 'PEN', s.issued_at, NOW(), 'SYNCED', 1, NOW()
                    FROM sales s
                    WHERE NOT EXISTS (SELECT 1 FROM sale_payments sp WHERE sp.sale_id = s.id)
                    SQL,
                ['payment_method_id' => $paymentMethodId],
            );
        }

        $connection = $this->connectionRegistry->getConnection();
        $connection->executeStatement('DELETE FROM branch_daily_summary');
        $connection->executeStatement(<<<'SQL'
            INSERT INTO branch_daily_summary
                (summary_date, branch_id, sales_count, gross_sales, discount_total, tax_total, net_sales, refunds_total)
            SELECT
                DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')), branch_id, COUNT(*),
                COALESCE(SUM(grand_total), 0), COALESCE(SUM(discount_total), 0),
                COALESCE(SUM(tax_total), 0), COALESCE(SUM(grand_total - tax_total), 0), 0
            FROM sales
            WHERE status != 'VOIDED'
            GROUP BY DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')), branch_id
            SQL);

        foreach ($connection->fetchFirstColumn('SELECT DISTINCT branch_id FROM sales') as $branchId) {
            $this->dashboardRepository->invalidate((string) $branchId);
        }
    }

    /** Open the cash register used by the seeded warehouse, ready for POS. */
    private function openPresentationCashSession(User $user): void
    {
        $warehouse = $this->warehouseRepository->findOneBy([]);
        $branch = $warehouse?->getBranch();
        if ($branch === null) {
            return;
        }

        $cashRegister = $this->cashRegisterRepository->findOneBy(['branch' => $branch]);
        if ($cashRegister === null) {
            return;
        }

        $now = new \DateTimeImmutable();
        $userId = $user->getId();
        if ($userId === null) {
            throw new \RuntimeException('No se pudo resolver el usuario de presentación para abrir caja.');
        }
        $session = (new CashSession())
            ->setCashRegister($cashRegister)
            ->setBranch($branch)
            // TestDataSeeder clears the ORM context after bulk inserts.
            ->setOpenedBy($this->entityManager->getReference(User::class, $userId))
            ->setStatus('OPEN')
            ->setOpeningAmount('200.00')
            ->setOpenedAt($now)
            ->setCreatedAt($now);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $this->connectionRegistry->getConnection()->executeStatement(
            'INSERT INTO cash_movements (cash_session_id, movement_type, amount, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$session->getId(), 'OPENING', '200.00', 'Saldo inicial de demostración', $userId],
        );
    }
}

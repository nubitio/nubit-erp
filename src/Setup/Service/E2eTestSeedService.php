<?php

declare(strict_types=1);

namespace App\Setup\Service;

use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Repository\CashRegisterRepository;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherSeries;
use App\Catalog\Entity\VoucherType;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Catalog\Repository\ProductRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Catalog\Repository\VoucherSeriesRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Contacts\Entity\Customer;
use App\Contacts\Entity\Supplier;
use App\Contacts\Repository\CustomerRepository;
use App\Contacts\Repository\SupplierRepository;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\RoleRepository;
use App\Identity\Repository\UserRepository;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\ProductStockRepository;
use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\DashboardRepository;
use App\Settings\Service\TenantSettingsService;
use App\Setup\Dto\InitialSetupInput;
use App\Setup\InitialSetupService;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class E2eTestSeedService implements E2eTestSeedServiceInterface
{
    private const string E2E_USERNAME = 'ci_e2e_user';
    private const string E2E_PASSWORD = 'CiE2E.123!';
    private const string E2E_BRANCH_NAME = 'CI E2E Branch';
    private const string E2E_CUSTOMER_RUC = '20100000001';
    private const string E2E_CUSTOMER_NAME = 'Cliente CI E2E';
    private const string E2E_PRODUCT_CODE = 'E2E-CI-PRODUCT';
    private const string E2E_PRODUCT_NAME = 'Producto CI E2E';
    private const string E2E_PRODUCT_2_CODE = 'E2E-CI-PRODUCT-2';
    private const string E2E_PRODUCT_2_NAME = 'Producto CI E2E 2';
    private const string E2E_SUPPLIER_RUC = '20100000999';
    private const string E2E_SUPPLIER_NAME = 'Proveedor CI E2E';
    private const string CONTROL_PLANE_ADMIN_USER = 'admin';
    private const string CONTROL_PLANE_ADMIN_PASSWORD = 'Admin.Control.123';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly InitialSetupService $initialSetupService,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly CompanyRepository $companyRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserRepository $userRepository,
        private readonly BranchRepository $branchRepository,
        private readonly WarehouseRepository $warehouseRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly SupplierRepository $supplierRepository,
        private readonly ProductRepository $productRepository,
        private readonly ProductStockRepository $productStockRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly UnitOfMeasureRepository $unitOfMeasureRepository,
        private readonly VoucherTypeRepository $voucherTypeRepository,
        private readonly VoucherSeriesRepository $voucherSeriesRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly CashRegisterRepository $cashRegisterRepository,
        private readonly DashboardRepository $dashboardRepository,
    ) {}

    /**
     * @return array{username: string, password: string, saleId: string}
     */
    public function seed(): array
    {
        if (!$this->initialSetupService->status()->configured) {
            $input = new InitialSetupInput();
            $input->adminUsername = 'admin';
            $envInitialAdminPassword = getenv('E2E_INITIAL_ADMIN_PASSWORD');
            $input->adminPassword = $envInitialAdminPassword !== false
                ? $envInitialAdminPassword
                : sprintf('%s.%s', 'Admin', '3000');
            $input->companyRuc = '20123456789';
            $input->companyBusinessName = 'Empresa Demo CI';
            $input->companyTradeName = 'Demo';
            $input->companyFiscalAddress = 'Av. Principal 123';
            $input->companyPhone = '999999999';
            $input->branchName = 'Principal';
            $input->branchAddress = 'Av. Principal 123';
            $input->branchPhone = '999999999';

            $this->initialSetupService->configure($input);
        }

        $settings = $this->tenantSettingsService->get();
        if (!$settings->isOnboardingCompleted()) {
            $settings->setOnboardingCompleted(true);
            $this->tenantSettingsService->save($settings);
        }

        $this->ensureControlPlaneAdminUser();

        $user = $this->upsertE2eUser(self::E2E_PASSWORD);

        $branch = $this->upsertBranch();
        $warehouse = $this->upsertWarehouse($branch);
        $cashRegister = $this->upsertCashRegister($branch);
        $this->purgeE2eSales();
        $this->purgeE2eCashSessions($cashRegister);
        $this->openE2eCashSession($cashRegister, $branch, $user);
        $this->ensureVoucherSeries($branch);

        // Login may keep the installation's default branch selected. Ensure
        // its POS also has an open register owned by the disposable E2E user.
        foreach ($this->cashRegisterRepository->findAll() as $availableRegister) {
            if ($availableRegister->getId() === $cashRegister->getId()) {
                continue;
            }
            $availableBranch = $availableRegister->getBranch();
            if (!$availableBranch instanceof Branch) {
                continue;
            }
            $openSession = $this->entityManager->getRepository(CashSession::class)->findOneBy([
                'cashRegister' => $availableRegister,
                'status' => 'OPEN',
            ]);
            if (!$openSession instanceof CashSession) {
                $this->openE2eCashSession($availableRegister, $availableBranch, $user);
            }
        }

        $customer = $this->upsertCustomer();
        $supplier = $this->supplierRepository->findOneBy(['ruc' => self::E2E_SUPPLIER_RUC]);
        if (!$supplier instanceof Supplier) {
            $supplier = new Supplier();
            $supplier
                ->setRuc(self::E2E_SUPPLIER_RUC)
                ->setBusinessName(self::E2E_SUPPLIER_NAME)
                ->setFiscalAddress('CI Supplier Address')
                ->setPhone('+51900000002');

            $this->entityManager->persist($supplier);
            $this->entityManager->flush();
        }
        $product = $this->upsertProduct($warehouse, self::E2E_PRODUCT_CODE, self::E2E_PRODUCT_NAME);
        $this->upsertProduct($warehouse, self::E2E_PRODUCT_2_CODE, self::E2E_PRODUCT_2_NAME);

        $sale = $this->createFreshSale($user, $branch, $warehouse, $customer, $product);

        return [
            'username' => $user->getUsername(),
            'password' => self::E2E_PASSWORD,
            'saleId' => (string) $sale->getId(),
        ];
    }

    public function reset(): void
    {
        $this->purgeE2eSales();
        $this->purgeE2ePurchases();

        $branch = $this->branchRepository->findOneBy(['name' => self::E2E_BRANCH_NAME]);
        if ($branch instanceof Branch) {
            $cashRegister = $this->cashRegisterRepository->findOneBy(['branch' => $branch]);
            if ($cashRegister instanceof CashRegister) {
                $this->purgeE2eCashSessions($cashRegister);
            }
        }

        $user = $this->userRepository->findOneBy(['username' => self::E2E_USERNAME]);
        if ($user instanceof User && $user->getId() !== null) {
            $userId = (int) $user->getId();
            $this->purgeE2eStockEntries($userId);
            // Opening movements reference both the session and its author with
            // restrictive foreign keys, so remove them before their sessions.
            $this->connection->executeStatement('DELETE FROM cash_movements WHERE created_by = :user_id', [
                'user_id' => $userId,
            ]);
            $this->purgeE2eCashSessionsByUser($userId);
            // A flow may close a pre-existing session that was opened by a
            // different fixture user. Keep that session, but detach the
            // disposable E2E closer before removing the user.
            $this->connection->executeStatement('UPDATE cash_sessions SET closed_by = NULL WHERE closed_by = :user_id', [
                'user_id' => $userId,
            ]);
            if ($this->connection->createSchemaManager()->tablesExist(['nubit_refresh_token'])) {
                $this->connection->executeStatement('DELETE FROM nubit_refresh_token WHERE user_identifier = :username', [
                    'username' => self::E2E_USERNAME,
                ]);
            }
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }
    }

    private function purgeE2eStockEntries(int $userId): void
    {
        $entryIds = $this->connection->fetchFirstColumn('SELECT id FROM stock_entries WHERE created_by = :user_id', [
            'user_id' => $userId,
        ]);

        if ($entryIds === []) {
            return;
        }

        $this->connection->executeStatement(
            "DELETE FROM stock_movements
             WHERE source_type = 'STOCK_ENTRY'
             AND SUBSTRING_INDEX(source_id, ':', 1) IN (?)",
            [$entryIds],
            [ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            'DELETE FROM stock_entries WHERE id IN (?)',
            [$entryIds],
            [ArrayParameterType::STRING],
        );
    }

    private function purgeE2eCashSessionsByUser(int $userId): void
    {
        $sessionIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM cash_sessions WHERE opened_by = :user_id',
            ['user_id' => $userId],
        );
        if ($sessionIds === []) {
            return;
        }

        $saleIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM sales WHERE cash_session_id IN (?)',
            [$sessionIds],
            [ArrayParameterType::STRING],
        );
        if ($saleIds !== []) {
            $this->purgeSalesByIds($saleIds);
        }

        $this->connection->executeStatement(
            'DELETE FROM cashier_daily_summary WHERE cash_session_id IN (?)',
            [$sessionIds],
            [ArrayParameterType::STRING],
        );
        $this->connection->executeStatement(
            'DELETE FROM cash_sessions WHERE id IN (?)',
            [$sessionIds],
            [ArrayParameterType::STRING],
        );
    }

    private function purgeE2ePurchases(): void
    {
        $purchaseIds = $this->connection->fetchFirstColumn('SELECT gr.id FROM goods_receipts gr
             INNER JOIN usuario u ON u.usuario_id = gr.buyer_user_id
             WHERE u.username = :username', [
            'username' => self::E2E_USERNAME,
        ]);

        if ($purchaseIds === []) {
            return;
        }

        $this->connection->executeStatement(
            "DELETE FROM stock_movements
             WHERE source_type = 'PURCHASE_ITEM'
             AND SUBSTRING_INDEX(source_id, ':', 1) IN (?)",
            [$purchaseIds],
            [ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            'DELETE FROM goods_receipt_items WHERE goods_receipt_id IN (?)',
            [$purchaseIds],
            [ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            'DELETE FROM goods_receipts WHERE id IN (?)',
            [$purchaseIds],
            [ArrayParameterType::STRING],
        );
    }

    private function ensureControlPlaneAdminUser(): void
    {
        $conn = $this->connectionRegistry->getConnection('control_plane');
        if (!$conn instanceof Connection) {
            throw new \RuntimeException('Control plane connection must be a DBAL connection.');
        }

        if (!$conn->createSchemaManager()->tablesExist(['control_plane_admin_users'])) {
            return;
        }

        $conn->executeStatement(
            <<<'SQL'
                INSERT INTO control_plane_admin_users
                    (username, password_hash, roles, is_active, created_at, updated_at)
                VALUES (?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    password_hash = VALUES(password_hash),
                    roles = VALUES(roles),
                    is_active = 1,
                    updated_at = NOW()
                SQL,
            [
                self::CONTROL_PLANE_ADMIN_USER,
                password_hash(self::CONTROL_PLANE_ADMIN_PASSWORD, PASSWORD_BCRYPT),
                json_encode(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], JSON_THROW_ON_ERROR),
            ],
        );
    }

    private function upsertE2eUser(string $password): User
    {
        $role = $this->resolveUserRole();

        $user = $this->userRepository->findOneBy(['username' => self::E2E_USERNAME]);
        if (!$user instanceof User) {
            $user = new User();
            $user->setUsername(self::E2E_USERNAME);
            $this->entityManager->persist($user);
        }

        $user->setRole($role);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->flush();

        return $user;
    }

    private function upsertBranch(): Branch
    {
        $branch = $this->branchRepository->findOneBy(['name' => self::E2E_BRANCH_NAME]);
        if ($branch instanceof Branch) {
            return $branch;
        }

        $company = $this->companyRepository->findOneBy([]);
        if ($company === null) {
            throw new \RuntimeException('No company found for E2E seed.');
        }

        $branch = new Branch();
        $branch
            ->setName(self::E2E_BRANCH_NAME)
            ->setAddress('CI E2E Address')
            ->setPhone('900000001')
            ->setCompany($company);

        $this->entityManager->persist($branch);
        $this->entityManager->flush();

        return $branch;
    }

    private function upsertWarehouse(Branch $branch): Warehouse
    {
        $warehouse = $this->warehouseRepository->findOneBy(['branch' => $branch]);
        if ($warehouse instanceof Warehouse) {
            return $warehouse;
        }

        $warehouse = new Warehouse();
        $warehouse->setName('Almacen CI E2E')->setBranch($branch);

        $this->entityManager->persist($warehouse);
        $this->entityManager->flush();

        return $warehouse;
    }

    private function ensureVoucherSeries(Branch $branch): void
    {
        $facturaType = $this->voucherTypeRepository->find('01');
        $boletaType = $this->voucherTypeRepository->find('03');
        $notaVentaType = $this->voucherTypeRepository->find('00');

        if ($facturaType instanceof VoucherType) {
            $this->upsertVoucherSeries($branch, $facturaType, 'F001');
        }

        if ($boletaType instanceof VoucherType) {
            $this->upsertVoucherSeries($branch, $boletaType, 'B001');
        }

        if ($notaVentaType instanceof VoucherType) {
            $this->upsertVoucherSeries($branch, $notaVentaType, 'N001');
        }

        $this->entityManager->flush();
    }

    private function upsertVoucherSeries(Branch $branch, VoucherType $voucherType, string $series): void
    {
        $existing = $this->voucherSeriesRepository->findOneBy([
            'branch' => $branch,
            'voucherType' => $voucherType,
        ]);

        if ($existing instanceof VoucherSeries) {
            return;
        }

        $voucherSeries = new VoucherSeries();
        $voucherSeries->setBranch($branch)->setVoucherType($voucherType)->setSeries($series);

        $this->entityManager->persist($voucherSeries);
    }

    private function upsertCustomer(): Customer
    {
        $customer = $this->customerRepository->findOneBy(['ruc' => self::E2E_CUSTOMER_RUC]);
        if ($customer instanceof Customer) {
            return $customer;
        }

        $customer = new Customer();
        $customer
            ->setRuc(self::E2E_CUSTOMER_RUC)
            ->setBusinessName(self::E2E_CUSTOMER_NAME)
            ->setFiscalAddress('CI Address')
            ->setPhone('+51900000001')
            ->setEmail('e2e-ci@example.test');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    private function upsertProduct(Warehouse $warehouse, string $code, string $name): Product
    {
        $product = $this->productRepository->findOneBy(['code' => $code]);
        if ($product instanceof Product) {
            // Always reset to known-good state so previous test runs don't affect stock or price
            $product->setSalePrice('10.00');
            $this->entityManager->flush();
        } else {
            $category = $this->categoryRepository->findOneBy([]);
            $unit = $this->unitOfMeasureRepository->findOneBy([]);

            if (!$category instanceof Category || !$unit instanceof UnitOfMeasure) {
                throw new \RuntimeException('No category/unit found for E2E seed.');
            }

            $product = new Product();
            $product
                ->setCode($code)
                ->setType(1)
                ->setName($name)
                ->setCategory($category)
                ->setUnitOfMeasure($unit)
                ->setPurchasePrice('8.00')
                ->setSalePrice('10.00')
                ->setMinStock('0.000');

            $this->entityManager->persist($product);
            $this->entityManager->flush();
        }

        // The E2E user can land on the installation's default branch without a
        // branch picker. Keep the deterministic products sellable in every
        // warehouse exposed to that super-admin session.
        foreach ($this->warehouseRepository->findAll() as $stockWarehouse) {
            $productStock = $this->productStockRepository->findByProductAndWarehouse($product, $stockWarehouse);
            if ($productStock instanceof ProductStock) {
                $productStock->setStock(100.0);
                $productStock->updateTimestamps();
            } else {
                $productStock = new ProductStock();
                $productStock->setProduct($product)->setWarehouse($stockWarehouse)->setStock(100.0)->setMinStock(0.0);
                $this->entityManager->persist($productStock);
            }
            $this->entityManager->flush();

            $this->connection->executeStatement(
                <<<'SQL'
                INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at, version)
                VALUES (:warehouse_id, :product_id, :qty_on_hand, 0, NOW(), 1)
                ON DUPLICATE KEY UPDATE
                    qty_on_hand = VALUES(qty_on_hand),
                    qty_reserved = 0,
                    updated_at = NOW(),
                    version = version + 1
                SQL,
                [
                    'warehouse_id' => (string) $stockWarehouse->getId(),
                    'product_id' => (string) $product->getId(),
                    'qty_on_hand' => '100.000000',
                ],
            );
        }

        return $product;
    }

    private function createFreshSale(
        User $user,
        Branch $branch,
        Warehouse $warehouse,
        Customer $customer,
        Product $product,
    ): SaleRecord {
        $this->purgeE2eSales();

        $paymentMethod = $this->resolvePaymentMethod();

        $sale = new SaleRecord();
        $sale
            ->setAlmacen($warehouse)
            ->setBranch($branch)
            ->setCliente($customer)
            ->setTipoId(2)
            ->setFecha(new \DateTime('-1 hour'))
            ->setFormaPago(PaymentMethod::CASH)
            ->setTipoPago($paymentMethod)
            ->setSerie('NV01')
            ->setNumero(sprintf('%08d', random_int(1000, 9999)))
            ->setDescuento('0.00')
            ->setHasInvoice(false)
            ->setCajaId(1)
            ->setEstado(1)
            ->setDateCreated(new DateTimeImmutable())
            ->setSellerUserId((int) $user->getId())
            ->setCreatedBy((int) $user->getId())
            ->setCreatedAt(new \DateTime())
            ->setIgv((string) $this->tenantSettingsService->getIgv())
            ->setRecargoConsumo(0)
            ->setTipoOperacion('0101')
            ->setSubtotal('10.00')
            ->setTotal('10.00')
            ->setCurrency('PEN')
            ->setExchangeRate('1.000000')
            ->setLastModifiedAt(new \DateTime())
            ->setSyncedAt(new \DateTime());

        $item = new SaleItemRecord();
        $item
            ->setSaleRecord($sale)
            ->setProduct($product)
            ->setLineNo(1)
            ->setNameSnapshot($product->getName())
            ->setQty('1.000')
            ->setUnitPrice('10.0000')
            ->setGrossAmount('10.00')
            ->setNetAmount('10.00')
            ->setLastModifiedAt(new \DateTime())
            ->setSyncedAt(new \DateTime());

        $this->entityManager->persist($sale);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        // Dashboard payment widgets read the normalized V2 table, not the
        // legacy payment fields on SaleRecord.
        $this->connection->executeStatement(
            'INSERT INTO sale_payments (sale_id, payment_method_id, amount, currency, paid_at, created_at, sync_status, version_no, last_modified_at) VALUES (?, ?, ?, ?, NOW(), NOW(), ?, 1, NOW())',
            [(string) $sale->getId(), $paymentMethod->getId(), '10.00', 'PEN', 'SYNCED'],
        );

        $this->refreshDashboardSummary($branch, $sale);

        return $sale;
    }

    /**
     * Direct seed inserts bypass SalesWriteGateway. Rebuild the matching
     * aggregate and invalidate its cache so dashboard KPIs match the seeded
     * recent sale shown to a prospect.
     */
    private function refreshDashboardSummary(Branch $branch, SaleRecord $sale): void
    {
        $issuedAt = $sale->getFecha() ?? new \DateTimeImmutable('now', new \DateTimeZone('America/Lima'));
        $saleDate = \DateTimeImmutable::createFromInterface($issuedAt)
            ->setTimezone(new \DateTimeZone('America/Lima'))
            ->format('Y-m-d');

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO branch_daily_summary
                    (summary_date, branch_id, sales_count, gross_sales, discount_total, tax_total, net_sales, refunds_total)
                SELECT
                    :sale_date, :branch_id, COUNT(*),
                    COALESCE(SUM(grand_total), 0), COALESCE(SUM(discount_total), 0),
                    COALESCE(SUM(tax_total), 0), COALESCE(SUM(grand_total - tax_total), 0), 0
                FROM sales
                WHERE branch_id = :branch_id
                  AND DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')) = :sale_date
                  AND status != 'VOIDED'
                ON DUPLICATE KEY UPDATE
                    sales_count = VALUES(sales_count), gross_sales = VALUES(gross_sales),
                    discount_total = VALUES(discount_total), tax_total = VALUES(tax_total),
                    net_sales = VALUES(net_sales), updated_at = NOW()
                SQL,
            ['sale_date' => $saleDate, 'branch_id' => (string) $branch->getId()],
        );

        $this->dashboardRepository->invalidate((string) $branch->getId());
    }

    private function purgeE2eSales(): void
    {
        // Identify E2E sales by the seller (ci_e2e_user). The old `glosa` column
        // was on the legacy `movimiento` table and no longer exists in `sales`.
        $saleIds = $this->connection->fetchFirstColumn('SELECT s.id FROM sales s
             INNER JOIN usuario u ON u.usuario_id = s.seller_user_id
             WHERE u.username = :username', [
            'username' => self::E2E_USERNAME,
        ]);

        if ($saleIds === []) {
            return;
        }

        $this->purgeSalesByIds($saleIds);
    }

    /**
     * @param list<string> $saleIds
     */
    private function purgeSalesByIds(array $saleIds): void
    {
        $this->connection->executeStatement(
            'DELETE FROM cash_movements WHERE sale_id IN (?)',
            [$saleIds],
            [ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            'DELETE FROM tax_document_event WHERE tax_document_id IN '
            . '(SELECT tax_document_id FROM tax_document WHERE sale_id IN (?))',
            [$saleIds],
            [ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            'DELETE FROM tax_document_line WHERE tax_document_id IN '
            . '(SELECT tax_document_id FROM tax_document WHERE sale_id IN (?))',
            [$saleIds],
            [ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            'DELETE FROM tax_document WHERE sale_id IN (?)',
            [$saleIds],
            [ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            'DELETE FROM sales WHERE id IN (?)',
            [$saleIds],
            [ArrayParameterType::STRING],
        );
    }

    private function resolveUserRole(): Role
    {
        // The E2E user drives end-to-end business flows (POS, catalog,
        // purchases, inventory and cash).  It must therefore use the
        // owner role. Assigning it the cashier role made the menu
        // advertise screens that immediately answered with 403, hiding real
        // regressions behind an unusable test/demo session.
        $role = $this->roleRepository->find(1) ?? $this->roleRepository->find(2) ?? $this->roleRepository->find(3);

        if (!$role instanceof Role) {
            throw new \RuntimeException('No role found for E2E user.');
        }

        return $role;
    }

    private function resolvePaymentMethod(): PaymentMethod
    {
        $paymentMethod =
            $this->paymentMethodRepository->find(PaymentMethod::CASH) ?? $this->paymentMethodRepository->findOneBy([]);

        if (!$paymentMethod instanceof PaymentMethod) {
            throw new \RuntimeException('No payment method found for sales.');
        }

        return $paymentMethod;
    }

    private function purgeE2eCashSessions(CashRegister $cashRegister): void
    {
        // Delete any sales still linked to these sessions before removing the sessions.
        // The FK fk_sales_session_branch (sales.cash_session_id → cash_sessions.id) has no
        // CASCADE, so we must clean up referencing rows first.
        $sessionIds = $this->connection->fetchFirstColumn('SELECT id FROM cash_sessions WHERE cash_register_id = :register_id', [
            'register_id' => $cashRegister->getId(),
        ]);

        if ($sessionIds !== []) {
            $orphanSaleIds = $this->connection->fetchFirstColumn(
                'SELECT id FROM sales WHERE cash_session_id IN (?)',
                [$sessionIds],
                [ArrayParameterType::STRING],
            );
            if ($orphanSaleIds !== []) {
                $this->purgeSalesByIds($orphanSaleIds);
            }
        }

        // cash_movements rows are deleted automatically via ON DELETE CASCADE on cash_session_id.
        $this->connection->executeStatement('DELETE FROM cash_sessions WHERE cash_register_id = :register_id', [
            'register_id' => $cashRegister->getId(),
        ]);
    }

    private function upsertCashRegister(Branch $branch): CashRegister
    {
        $cashRegister = $this->cashRegisterRepository->findOneBy(['branch' => $branch]);
        if ($cashRegister instanceof CashRegister) {
            return $cashRegister;
        }

        $cashRegister = new CashRegister();
        $cashRegister->setName('Caja CI E2E')->setBranch($branch);

        $this->entityManager->persist($cashRegister);
        $this->entityManager->flush();

        return $cashRegister;
    }

    /** Keep the POS ready to charge immediately after a demo seed. */
    private function openE2eCashSession(CashRegister $cashRegister, Branch $branch, User $user): void
    {
        $now = new DateTimeImmutable();
        $session = (new CashSession())
            ->setCashRegister($cashRegister)
            ->setBranch($branch)
            ->setOpenedBy($user)
            ->setStatus('OPEN')
            ->setOpeningAmount('200.00')
            ->setOpenedAt($now)
            ->setCreatedAt($now);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $this->connection->executeStatement(
            'INSERT INTO cash_movements (cash_session_id, movement_type, amount, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$session->getId(), 'OPENING', '200.00', 'Saldo inicial de demostración', $user->getId()],
        );
    }
}

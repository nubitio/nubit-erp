<?php

declare(strict_types=1);

namespace App\Setup;

use App\AccountsPayable\Entity\ExpenseCategory;
use App\AccountsPayable\Repository\ExpenseCategoryRepository;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Enum\CashMovementType;
use App\CashRegister\Repository\CashMovementCategoryRepository;
use App\CashRegister\Repository\CashRegisterRepository;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use App\Catalog\Enum\SettlementBehavior;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Catalog\Repository\SunatCatalogRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Catalog\Service\VoucherSeriesAllocation;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\RoleRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Security\PermissionCatalog;
use App\Inventory\Entity\LogisticOperation;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\LogisticOperationRepository;
use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use App\Reporting\Service\TimeDimensionSeeder;
use App\Settings\Service\MenuSeedService;
use App\Settings\Service\SunatStateService;
use App\Settings\Service\TenantSettingsService;
use App\Settings\Service\UbigeoImportService;
use App\Setup\Dto\InitialSetupInput;
use App\Setup\Dto\SetupStatusDto;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Throwable;

readonly class InitialSetupService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
        private CompanyRepository $companyRepository,
        private BranchRepository $branchRepository,
        private RoleRepository $roleRepository,
        private LogisticOperationRepository $logisticOperationRepository,
        private PaymentMethodRepository $paymentMethodRepository,
        private CashMovementCategoryRepository $cashMovementCategoryRepository,
        private CashRegisterRepository $cashRegisterRepository,
        private ExpenseCategoryRepository $expenseCategoryRepository,
        private VoucherTypeRepository $voucherTypeRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
        private CategoryRepository $categoryRepository,
        private CustomerRepository $customerRepository,
        private SunatCatalogRepository $sunatCatalogRepository,
        private Connection $connection,
        private TenantSettingsService $tenantSettingsService,
        private SunatStateService $sunatStateService,
        private UbigeoImportService $ubigeoImportService,
        private TimeDimensionSeeder $timeDimensionSeeder,
        private MenuSeedService $menuSeedService,
    ) {}

    public function status(): SetupStatusDto
    {
        $userCount = $this->userRepository->count([]);
        $companyCount = $this->companyRepository->count([]);
        $branchCount = $this->branchRepository->count([]);

        return new SetupStatusDto(
            configured: $userCount > 0 && $companyCount > 0 && $branchCount > 0,
            userCount: $userCount,
            companyCount: $companyCount,
            branchCount: $branchCount,
        );
    }

    /**
     * @throws Exception|Throwable
     */
    public function configure(InitialSetupInput $input): SetupStatusDto
    {
        $status = $this->status();
        if ($status->configured || $status->userCount > 0) {
            throw new BadRequestHttpException('La configuración inicial ya fue completada.');
        }

        // No explicit outer transaction here on purpose. Wrapping this whole
        // sequence in a manual beginTransaction()/commit() forced every nested
        // $em->flush() into a DBAL savepoint (SAVEPOINT DOCTRINE_2, _3, …). The
        // AuditTrailListener runs a second $em->flush() inside postFlush, and
        // the seeders below (UbigeoImportService, TimeDimensionSeeder) open
        // their own transactions — the resulting savepoint nesting broke with
        // "SAVEPOINT DOCTRINE_2 does not exist" on a fresh tenant. Each unit
        // below (the ORM flush, each seeder) manages its own transaction, which
        // is enough: this only ever runs once, against an empty tenant DB, and
        // a failed run is recovered by dropping and re-provisioning the tenant.
        $superAdminRole = $this->ensureDefaultRoles();
        $this->seedDefaultRolePermissions();

        $company = new Company()
            ->setRuc($input->companyRuc)
            ->setBusinessName($input->companyBusinessName)
            ->setTradeName($input->companyTradeName)
            ->setFiscalAddress($input->companyFiscalAddress)
            ->setPhone($input->companyPhone);

        $this->entityManager->persist($company);

        $branch = new Branch()
            ->setName($input->branchName)
            ->setAddress($input->branchAddress)
            ->setPhone($input->branchPhone)
            ->setCompany($company);

        $this->entityManager->persist($branch);

        $warehouse = new Warehouse()
            ->setName('Almacén Principal')
            ->setBranch($branch);
        $this->entityManager->persist($warehouse);

        $user = new User()
            ->setUsername($input->adminUsername)
            ->setRole($superAdminRole);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $input->adminPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->seedSettings();
        $this->seedReferenceData();
        $this->seedUbigeoFromResource();
        $this->seedTimeDimension();
        $this->menuSeedService->seed();
        $this->ensureVoucherSeriesForBranch($branch);
        $this->ensureCashRegisterForBranch($branch);
        $this->assignFullMenuToRole(intval($superAdminRole->getId()));

        return $this->status();
    }

    private function ensureDefaultRoles(): Role
    {
        $roles = [
            1 => 'Super Administrador',
            2 => 'Administrador',
            3 => 'Cajero',
        ];

        foreach ($roles as $id => $name) {
            $this->connection->executeStatement(
                'INSERT INTO rol (rol_id, descripcion)
                VALUES (?, ?) ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)',
                [$id, $name],
            );
        }

        $superAdmin = $this->roleRepository->find(1) ?? $this->roleRepository->findOneBy(['name' => $roles[1]]);

        if (null === $superAdmin) {
            throw new RuntimeException('No se pudo preparar el rol de super administrador.');
        }

        return $superAdmin;
    }

    private function seedDefaultRolePermissions(): void
    {
        foreach (PermissionCatalog::keys() as $permission) {
            $this->connection->executeStatement('INSERT IGNORE INTO role_permission (role_id, permission) VALUES (2, ?)', [
                $permission,
            ]);
        }

        // What the point of sale needs and nothing else. `sales.view_own` instead of `sales.view`: after a
        // sale the POS prints the ticket, shows its SUNAT state and e-mails it, all on the cashier's own
        // sales, without opening the branch's sales ledger. Catalogue, customers and stock are read by the
        // POS itself (products, customer picker, stock per warehouse).
        $cashierPermissions = [
            'sales.view_own',
            'sales.create',
            'sales.collect',
            'pos.access',
            'pos.offline_sale',
            'cash.view',
            'cash.open',
            'cash.close',
            // Asks for and hands over change, pays a small expense, takes the surplus to the safe. The reasons
            // it can pick are only the manual ones (see CashMovementCategory::isManual).
            'cash.movement',
            'inventory.view',
            'catalog.view',
            'customers.view',
            'customers.create',
        ];
        foreach ($cashierPermissions as $permission) {
            $this->connection->executeStatement('INSERT IGNORE INTO role_permission (role_id, permission) VALUES (3, ?)', [
                $permission,
            ]);
        }
    }

    private function seedSettings(): void
    {
        try {
            $settings = $this->tenantSettingsService->get();
            $settings->setIgv(18.0);
            $settings->setRc(0.0);
            $settings->setAutoInvoice(true);
            $settings->setAutoPrint(true);
            $settings->setPrintLogo(true);
            $settings->setRequireCashRegister(true);
            $settings->setPrintMethod(\App\Settings\Enum\PrintMethod::WEBUSB);
            $settings->setDefaultCurrency(\App\Settings\Enum\DefaultCurrency::PEN);
            $settings->setNegativeStockPolicy(\App\Efact\Enum\StockPolicy::STRICT);
            $this->tenantSettingsService->save($settings);

            $sunat = $this->sunatStateService->get();
            $sunat->setSendEnabled(true);
            $sunat->setReceiptRequired(false);
            $sunat->setRejectionStreak(0);
            $sunat->setRejectionThreshold(3);
            $this->sunatStateService->save($sunat);
        } catch (Throwable) {
            // Entities might not be ready yet; fail silently to avoid blocking setup.
        }
    }

    /**
     * Grants the given role access to all menus.
     *
     * @throws Exception
     */
    private function assignFullMenuToRole(int $roleId): void
    {
        $menuIds = $this->connection->fetchFirstColumn('SELECT id FROM menu');

        foreach ($menuIds as $menuId) {
            $this->connection->executeStatement(
                'INSERT INTO menu_rol (menu_id, role_id)
                VALUES (?, ?) ON DUPLICATE KEY UPDATE menu_id = VALUES(menu_id), role_id = VALUES(role_id)',
                [$menuId, $roleId],
            );
        }
    }

    /**
     * Seed core reference data needed for first use.
     */
    private function seedReferenceData(): void
    {
        $this->getOrCreateUnitOfMeasure('NIU', 'Unidad', 1, 1.0, true);

        // Logistic operations (tipo_id: 1 entrada, 2 salida)
        $operations = [
            ['Venta',                        2],
            ['Devolución de venta',          1],
            ['Compra',                       1],
            ['Devolución de compra',         2],
            ['Ajuste de inventario entrada', 1],
            ['Ajuste de inventario salida',  2],
            ['Transferencia entrada',        1],
            ['Transferencia salida',         2],
        ];
        foreach ($operations as [$name, $type]) {
            $this->getOrCreateLogisticOperation($name, $type);
        }

        // Payment methods including digital wallets
        $paymentMethods = [
            'Efectivo',
            'Credito',
            'Tarjeta débito',
            'Tarjeta crédito',
            'Transferencia',
            'Yape',
            'Plin',
            'Link de pago',
            'Depósito',
        ];
        foreach ($paymentMethods as $method) {
            $this->getOrCreatePaymentMethod($method);
        }

        // Cash movement categories
        $cashMovementCategories = [
            [CashMovementCategory::CASH_SALE,           'Venta en efectivo',     CashMovementType::INCOME],
            [CashMovementCategory::CREDIT_PAYMENT,      'Cobro de crédito',      CashMovementType::INCOME],
            [CashMovementCategory::CASH_REPLENISHMENT,  'Reposición de caja',    CashMovementType::INCOME],
            [CashMovementCategory::OTHER_INCOME,        'Otros ingresos',        CashMovementType::INCOME],
            [CashMovementCategory::OVERAGE_ADJUSTMENT,  'Ajuste por sobrante',   CashMovementType::INCOME],
            [CashMovementCategory::PETTY_CASH_EXPENSE,  'Gasto de caja chica',   CashMovementType::EXPENSE],
            [CashMovementCategory::CUSTOMER_REFUND,     'Devolución al cliente', CashMovementType::EXPENSE],
            [CashMovementCategory::MANAGER_WITHDRAWAL,  'Retiro de efectivo',    CashMovementType::EXPENSE],
            [CashMovementCategory::BANK_DEPOSIT,        'Depósito bancario',     CashMovementType::EXPENSE],
            [CashMovementCategory::SHORTAGE_ADJUSTMENT, 'Ajuste por faltante',   CashMovementType::EXPENSE],
        ];
        foreach ($cashMovementCategories as [$code, $name, $type]) {
            $this->getOrCreateCashMovementCategory($code, $name, $type);
        }

        // Expense categories (accounts payable)
        $expenseCategories = [
            ['bank_fees',               'Comisiones bancarias'],
            ['utilities',               'Servicios básicos'],
            ['rent',                    'Alquiler'],
            ['taxes',                   'Impuestos y tasas'],
            ['other_operating_expense', 'Otros gastos operativos'],
        ];
        foreach ($expenseCategories as [$code, $name]) {
            $this->getOrCreateExpenseCategory($code, $name);
        }

        // Voucher types (SUNAT)
        $voucherTypes = [
            ['00', 'Nota de Venta'],
            ['01', 'Factura'],
            ['03', 'Boleta'],
            ['07', 'Nota de Crédito'],
            ['08', 'Nota de Débito'],
        ];
        foreach ($voucherTypes as [$code, $name]) {
            $this->getOrCreateVoucherType($code, $name);
        }

        // SUNAT catalog 51 (Tipo de operación)
        $operationTypes = [
            ['0101', 'Venta Interna'],
            ['0200', 'Exportación de bienes'],
            ['1001', 'Operación sujeta al Sistema de Pago de Obligaciones Tributarias con el Gobierno Central'],
            ['1004', 'Operación Sujeta a Detracción- Servicios de Transporte Carga'],
        ];
        foreach ($operationTypes as [$code, $name]) {
            $this->getOrCreateSunatCatalog(51, $code, $name);
        }

        // SUNAT catalog 59 (Medios de pago)
        $paymentMethods = [
            ['001', 'Depósito en cuenta'],
            ['002', 'Giro'],
            ['003', 'Transferencia de fondos'],
            ['004', 'Orden de pago'],
            ['005', 'Tarjeta de débito'],
            [
                '006',
                'Tarjeta de crédito emitida en el país por una empresa del sistema financiero',
            ],
            [
                '007',
                'Cheques con la cláusula de "NO NEGOCIABLE", "INTRANSFERIBLES", "NO A LA ORDEN" '
                    . 'u otra equivalente, a que se refiere el inciso g) del artículo 5° de la ley',
            ],
            ['008', 'Efectivo, por operaciones en las que no existe obligación de utilizar medio de pago'],
            ['009', 'Efectivo, en los demás casos'],
            ['010', 'Medios de pago usados en comercio exterior'],
            [
                '011',
                'Documentos emitidos por las EDPYMES y las cooperativas de ahorro y crédito '
                    . 'no autorizadas a captar depósitos del público',
            ],
            [
                '012',
                'Tarjeta de crédito emitida en el país o en el exterior por una empresa no '
                    . 'perteneciente al sistema financiero, cuyo objeto principal sea la emisión '
                    . 'y administración de tarjetas de crédito',
            ],
            ['013', 'Tarjetas de crédito emitidas en el exterior por empresas bancarias o financieras no domiciliadas'],
            ['101', 'Transferencias – Comercio exterior'],
            ['102', 'Cheques bancarios - Comercio exterior'],
            ['103', 'Orden de pago simple - Comercio exterior'],
            ['104', 'Orden de pago documentario - Comercio exterior'],
            ['105', 'Remesa simple - Comercio exterior'],
            ['106', 'Remesa documentaria - Comercio exterior'],
            ['107', 'Carta de crédito simple - Comercio exterior'],
            ['108', 'Carta de crédito documentario - Comercio exterior'],
            ['999', 'Otros medios de pago'],
        ];
        foreach ($paymentMethods as [$code, $name]) {
            $this->getOrCreateSunatCatalog(59, $code, $name);
        }

        // SUNAT catalog 54 (Detraccion bienes/servicios)
        $detractionCatalog = [
            ['001', 'Azúcar y melaza de caña', 10.0],
            ['003', 'Alcohol etílico', 10.0],
            ['004', 'Recursos hidrobiológicos', 4.0],
            ['005', 'Maíz amarillo duro', 4.0],
            ['007', 'Caña de azúcar', 10.0],
            ['008', 'Madera', 4.0],
            ['009', 'Arena y piedra.', 10.0],
            ['010', 'Residuos, subproductos, desechos, recortes y desperdicios', 15.0],
            ['012', 'Intermediación laboral y tercerización', 12.0],
            ['014', 'Carnes y despojos comestibles', 4.0],
            ['016', 'Aceite de pescado', 10.0],
            ['017', 'Harina, polvo y “pellets” de pescado, crustáceos, moluscos y demás invertebrados acuáticos', 4.0],
            ['019', 'Arrendamiento de bienes muebles', 10.0],
            ['020', 'Mantenimiento y reparación de bienes muebles', 12.0],
            ['021', 'Movimiento de carga', 10.0],
            ['022', 'Otros servicios empresariales', 12.0],
            ['023', 'Leche', 4.0],
            ['024', 'Comisión mercantil', 10.0],
            ['025', 'Fabricación de bienes por encargo', 10.0],
            ['026', 'Servicio de transporte de personas', 10.0],
            ['027', 'Servicio de transporte de carga', 4.0],
            ['028', 'Transporte de pasajeros', 10.0],
            ['030', 'Contratos de construcción', 4.0],
            ['031', 'Oro gravado con el IGV', 10.0],
            ['032', 'Paprika y otros frutos de los generos capsicum o pimienta', 10.0],
            ['034', 'Minerales metálicos no auríferos', 10.0],
            ['035', 'Bienes exonerados del IGV', 1.5],
            ['036', 'Oro y demás minerales metálicos exonerados del IGV', 1.5],
            ['037', 'Demás servicios gravados con el IGV', 12.0],
            ['039', 'Minerales no metálicos', 10.0],
            ['041', 'Plomo', 15.0],
        ];
        foreach ($detractionCatalog as [$code, $name, $percentage]) {
            $this->getOrCreateSunatCatalog(54, $code, $name, $percentage);
        }

        // Default category
        $this->getOrCreateCategory('General');

        // Default customer
        $this->getOrCreateCustomer('00000000', 'Cliente General');
    }

    private function getOrCreateLogisticOperation(string $name, int $type): void
    {
        $existing = $this->logisticOperationRepository->findOneBy(['name' => $name]);
        if ($existing instanceof LogisticOperation) {
            return;
        }

        $operation = new LogisticOperation()
            ->setName($name)
            ->setType($type);

        $this->entityManager->persist($operation);
        $this->entityManager->flush();
    }

    private function getOrCreatePaymentMethod(string $name): void
    {
        $behavior = $name === 'Credito' ? SettlementBehavior::RECEIVABLE : SettlementBehavior::COLLECTED;
        $existing = $this->paymentMethodRepository->findOneBy(['name' => $name]);
        if ($existing instanceof PaymentMethod) {
            return;
        }

        $paymentMethod = new PaymentMethod()
            ->setName($name)
            ->setSettlementBehavior($behavior);
        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();
    }

    private function getOrCreateCashMovementCategory(string $code, string $name, CashMovementType $type): void
    {
        $existing = $this->cashMovementCategoryRepository->findOneBy(['code' => $code]);
        if ($existing instanceof CashMovementCategory) {
            return;
        }

        $category = new CashMovementCategory()
            ->setCode($code)
            ->setName($name)
            ->setType($type)
            ->setIsSystem(true)
            ->setManual(in_array($code, CashMovementCategory::MANUAL_CODES, true))
            ->setActive(true);

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    private function getOrCreateExpenseCategory(string $code, string $name): void
    {
        $existing = $this->expenseCategoryRepository->findOneBy(['code' => $code]);
        if ($existing instanceof ExpenseCategory) {
            return;
        }

        $category = new ExpenseCategory()
            ->setCode($code)
            ->setName($name)
            ->setIsSystem(true)
            ->setActive(true);

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    private function getOrCreateVoucherType(string $code, string $name): void
    {
        $existing = $this->voucherTypeRepository->find($code);
        if ($existing instanceof VoucherType) {
            return;
        }

        $voucherType = new VoucherType()
            ->setCode($code)
            ->setName($name);
        $this->entityManager->persist($voucherType);
        $this->entityManager->flush();
    }

    private function getOrCreateSunatCatalog(int $catalog, string $code, string $name, ?float $percentage = null): void
    {
        $existing = $this->sunatCatalogRepository->findOneBy([
            'catalog' => $catalog,
            'code' => $code,
        ]);
        if ($existing instanceof SunatCatalog) {
            return;
        }

        $entry = new SunatCatalog()
            ->setCatalog($catalog)
            ->setCode($code)
            ->setDescription($name)
            ->setPercentage($percentage)
            ->setActive(true);

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    private function getOrCreateUnitOfMeasure(
        string $code,
        string $name,
        int $type,
        float $ratio,
        bool $reference,
    ): void {
        $existing = $this->unitOfMeasureRepository->findOneBy(['code' => $code]);
        if ($existing instanceof UnitOfMeasure) {
            return;
        }

        $unit = new UnitOfMeasure()
            ->setName($name)
            ->setCode($code)
            ->setType($type)
            ->setRatio((string) $ratio)
            ->setReference($reference);

        $this->entityManager->persist($unit);
        $this->entityManager->flush();
    }

    private function getOrCreateCategory(string $name): void
    {
        $existing = $this->categoryRepository->findOneBy(['name' => $name]);
        if ($existing instanceof Category) {
            return;
        }

        $category = new Category()->setName($name);
        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    private function getOrCreateCustomer(string $ruc, string $businessName): void
    {
        $existing = $this->customerRepository->findOneBy(['ruc' => $ruc]);
        if ($existing instanceof Customer) {
            return;
        }

        $customer = new Customer()
            ->setRuc($ruc)
            ->setBusinessName($businessName)
            ->setFiscalAddress('N/A');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();
    }

    private function seedUbigeoFromResource(): void
    {
        $file = realpath(__DIR__ . '/Resources/ubugeo.csv');
        if (!$file || !is_readable($file)) {
            return;
        }

        // Incluye cabecera, por eso skipHeader=true.
        $this->ubigeoImportService->import(filePath: $file, delimiter: ',', skipHeader: true, batchSize: 500);
    }

    private function seedTimeDimension(): void
    {
        $this->timeDimensionSeeder->seed(
            startDate: new DateTimeImmutable('2020-01-01'),
            endDate: new DateTimeImmutable('2040-01-01'),
            batchSize: 500,
        );
    }

    public function ensureDefaultVoucherSeriesForAllBranches(): void
    {
        foreach ($this->branchRepository->findAll() as $branch) {
            $this->ensureVoucherSeriesForBranch($branch);
        }
    }

    public function ensureVoucherSeriesForBranch(Branch $branch): void
    {
        foreach (['00' => 'N', '01' => 'F', '03' => 'B'] as $type => $prefix) {
            if (!$this->voucherTypeRepository->find($type) instanceof VoucherType) {
                continue;
            }
            VoucherSeriesAllocation::ensureForBranch($this->connection, (string) $branch->getId(), $type, $prefix);
        }
    }

    /**
     * seedSettings() turns on requireCashRegister, which blocks POS sales
     * until a register is open — without this, a freshly provisioned tenant
     * has no CashRegister at all and no way to sell.
     */
    private function ensureCashRegisterForBranch(Branch $branch): void
    {
        $existing = $this->cashRegisterRepository->findRealByBranchId((int) $branch->getId());
        if ($existing !== []) {
            return;
        }

        $cashRegister = new CashRegister()
            ->setBranch($branch)
            ->setName('Caja Principal');

        $this->entityManager->persist($cashRegister);
        $this->entityManager->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Setup;

use Throwable;
use RuntimeException;
use DateTimeImmutable;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Setup\InitialSetupInput;
use App\Dto\Setup\SetupStatusDto;
use App\Entity\Branch;
use App\Entity\CashMovementCategory;
use App\Entity\Category;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\LogisticOperation;
use App\Entity\PaymentMethod;
use App\Entity\Role;
use App\Entity\UnitOfMeasure;
use App\Entity\User;
use App\Entity\VoucherSeries;
use App\Entity\VoucherType;
use App\Entity\Warehouse;
use App\Entity\SunatCatalog;
use App\Enum\CashMovementType;
use App\Repository\BranchRepository;
use App\Repository\CashMovementCategoryRepository;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\CustomerRepository;
use App\Repository\LogisticOperationRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\RoleRepository;
use App\Repository\UnitOfMeasureRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherSeriesRepository;
use App\Repository\VoucherTypeRepository;
use App\Repository\SunatCatalogRepository;
use App\Service\MenuSeedService;
use App\Service\TimeDimensionSeeder;
use App\Service\UbigeoImportService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        private VoucherTypeRepository $voucherTypeRepository,
        private VoucherSeriesRepository $voucherSeriesRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
        private CategoryRepository $categoryRepository,
        private CustomerRepository $customerRepository,
        private SunatCatalogRepository $sunatCatalogRepository,
        private Connection $connection,
        private SettingsService $settingsService,
        private UbigeoImportService $ubigeoImportService,
        private TimeDimensionSeeder $timeDimensionSeeder,
        private MenuSeedService $menuSeedService,
    ) {
    }

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

        $this->connection->beginTransaction();
        try {
            $superAdminRole = $this->ensureDefaultRoles();

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
            $this->assignFullMenuToRole(intval($superAdminRole->getId()));

            $this->connection->commit();
        } catch (Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }

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
                [$id, $name]
            );
        }

        $superAdmin = $this->roleRepository->find(1) ?? $this->roleRepository->findOneBy(['name' => $roles[1]]);

        if (null === $superAdmin) {
            throw new RuntimeException('No se pudo preparar el rol de super administrador.');
        }

        return $superAdmin;
    }

    private function seedSettings(): void
    {
        try {
            $this->settingsService->set(Constants::TAX, 'int', '18');
            $this->settingsService->set(Constants::CONSUMPTION_TAX, 'int', '0');
            $this->settingsService->set(Constants::AUTO_INVOICE, 'bool', 'true');
            $this->settingsService->set(Constants::CHECK_STOCK, 'bool', 'false');
            $this->settingsService->set(Constants::PRINT_LOGO, 'bool', 'true');
            $this->settingsService->set(Constants::REQUIRE_CASH_REGISTER, 'bool', 'true');
            $this->settingsService->set(Constants::RECEIPT_REQUIRED, 'bool', 'false');
            $this->settingsService->set(Constants::SUNAT_SEND_ENABLED, 'bool', 'true');
            $this->settingsService->set(Constants::SUNAT_REJECTION_STREAK, 'int', '0');
            $this->settingsService->set(Constants::SUNAT_REJECTION_THRESHOLD, 'int', '3');
            $this->settingsService->set(Constants::SUNAT_SEND_RETRY_MAX_ATTEMPTS, 'int', '8');
            $this->settingsService->set(Constants::SUNAT_SEND_RETRY_BASE_SECONDS, 'int', '60');
            $this->settingsService->set(Constants::SUNAT_SEND_RETRY_MAX_SECONDS, 'int', '1800');
            $this->settingsService->set(Constants::SALES_V2_DUAL_WRITE_ENABLED, 'bool', 'true');
            $this->settingsService->set(Constants::SALES_V2_DUAL_WRITE_STRICT, 'bool', 'false');
            $this->settingsService->set(Constants::SALES_V2_MODE, 'string', 'dual_write');
            $this->settingsService->set(Constants::SALES_V2_CUTOVER_DATE, 'string', '');
            $this->settingsService->set('sales_v2_disable_legacy_cash_movement', 'bool', 'false');
            $this->settingsService->set('sales_v2_disable_legacy_stock_projection', 'bool', 'false');
        } catch (Throwable) {
            // Settings table might not be ready yet; fail silently to avoid blocking setup.
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
                [$menuId, $roleId]
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
            ['Venta', 2],
            ['Devolución de venta', 1],
            ['Compra', 1],
            ['Devolución de compra', 2],
            ['Ajuste de inventario entrada', 1],
            ['Ajuste de inventario salida', 2],
            ['Transferencia entrada', 1],
            ['Transferencia salida', 2],
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
            [CashMovementCategory::CASH_SALE, 'Venta en efectivo', CashMovementType::INCOME],
            [CashMovementCategory::CREDIT_PAYMENT, 'Cobro de crédito', CashMovementType::INCOME],
            [CashMovementCategory::CASH_REPLENISHMENT, 'Reposición de caja', CashMovementType::INCOME],
            [CashMovementCategory::OTHER_INCOME, 'Otros ingresos', CashMovementType::INCOME],
            [CashMovementCategory::OVERAGE_ADJUSTMENT, 'Ajuste por sobrante', CashMovementType::INCOME],
            [CashMovementCategory::PETTY_CASH_EXPENSE, 'Gasto de caja chica', CashMovementType::EXPENSE],
            [CashMovementCategory::CUSTOMER_REFUND, 'Devolución al cliente', CashMovementType::EXPENSE],
            [CashMovementCategory::MANAGER_WITHDRAWAL, 'Retiro de efectivo', CashMovementType::EXPENSE],
            [CashMovementCategory::BANK_DEPOSIT, 'Depósito bancario', CashMovementType::EXPENSE],
            [CashMovementCategory::SHORTAGE_ADJUSTMENT, 'Ajuste por faltante', CashMovementType::EXPENSE],
        ];
        foreach ($cashMovementCategories as [$code, $name, $type]) {
            $this->getOrCreateCashMovementCategory($code, $name, $type);
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
            [
                '1001',
                'Operación sujeta al Sistema de Pago de Obligaciones Tributarias con el Gobierno Central',
            ],
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
        $existing = $this->paymentMethodRepository->findOneBy(['name' => $name]);
        if ($existing instanceof PaymentMethod) {
            return;
        }

        $paymentMethod = new PaymentMethod()->setName($name);
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

    private function getOrCreateSunatCatalog(
        int $catalog,
        string $code,
        string $name,
        ?float $percentage = null
    ): void {
        $existing = $this->sunatCatalogRepository->findOneBy([
            'catalog' => $catalog,
            'code' => $code,
        ]);
        if ($existing instanceof SunatCatalog) {
            return;
        }

        $entry = (new SunatCatalog())
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
        bool $reference
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
        $file = realpath(__DIR__ . '/../Resources/ubugeo.csv');
        if (!$file || !is_readable($file)) {
            return;
        }

        // Incluye cabecera, por eso skipHeader=true.
        $this->ubigeoImportService->import(
            filePath: $file,
            delimiter: ',',
            skipHeader: true,
            batchSize: 500,
        );
    }

    private function seedTimeDimension(): void
    {
        $this->timeDimensionSeeder->seed(
            startDate: new DateTimeImmutable('2020-01-01'),
            endDate: new DateTimeImmutable('2040-01-01'),
            batchSize: 500,
        );
    }

    private function ensureVoucherSeriesForBranch(Branch $branch): void
    {
        $seriesList = [
            ['code' => '01', 'series' => 'F001'], // Factura
            ['code' => '03', 'series' => 'B001'], // Boleta
            ['code' => '00', 'series' => 'N001'], // Nota de venta
        ];

        $needsFlush = false;
        foreach ($seriesList as $definition) {
            $voucherType = $this->voucherTypeRepository->find($definition['code']);
            if (!$voucherType instanceof VoucherType) {
                continue;
            }

            $existing = $this->voucherSeriesRepository->findOneBy([
                'branch' => $branch,
                'voucherType' => $voucherType,
                'series' => $definition['series'],
            ]);

            if ($existing instanceof VoucherSeries) {
                continue;
            }

            $voucherSeries = new VoucherSeries()
                ->setBranch($branch)
                ->setVoucherType($voucherType)
                ->setSeries($definition['series']);

            $this->entityManager->persist($voucherSeries);
            $needsFlush = true;
        }

        if ($needsFlush) {
            $this->entityManager->flush();
        }
    }
}

<?php

namespace App\Setup;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Setup\InitialSetupInput;
use App\Dto\Setup\SetupStatusDto;
use App\Entity\Branch;
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
use App\Service\UbigeoImportService;
use App\Service\TimeDimensionSeeder;
use App\Service\MenuSeedService;
use App\Repository\BranchRepository;
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
        private VoucherTypeRepository $voucherTypeRepository,
        private VoucherSeriesRepository $voucherSeriesRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
        private CategoryRepository $categoryRepository,
        private CustomerRepository $customerRepository,
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
     * @throws Exception|\Throwable
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

            $company = (new Company())
                ->setRuc($input->companyRuc)
                ->setBusinessName($input->companyBusinessName)
                ->setTradeName($input->companyTradeName)
                ->setFiscalAddress($input->companyFiscalAddress)
                ->setPhone($input->companyPhone);

            $this->entityManager->persist($company);

            $branch = (new Branch())
                ->setName($input->branchName)
                ->setAddress($input->branchAddress)
                ->setPhone($input->branchPhone)
                ->setCompany($company);

            $this->entityManager->persist($branch);

            $warehouse = (new Warehouse())
                ->setName('Almacén Principal')
                ->setBranch($branch);
            $this->entityManager->persist($warehouse);

            $user = (new User())
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
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
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
            throw new \RuntimeException('No se pudo preparar el rol de super administrador.');
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
        } catch (\Throwable) {
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
            ['Venta', 1],
            ['Compra', 2],
            ['Devolución de compra', 1],
            ['Devolución de venta', 2],
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

        // Default category
        $this->getOrCreateCategory('General');

        // Default customer
        $this->getOrCreateCustomer('00000000', 'Cliente General');
    }

    private function getOrCreateLogisticOperation(string $name, int $type): void
    {
        $existing = $this->logisticOperationRepository->findOneBy(['name' => $name]);
        if ($existing) {
            return;
        }

        $operation = (new LogisticOperation())
            ->setName($name)
            ->setType($type);

        $this->entityManager->persist($operation);
        $this->entityManager->flush();
    }

    private function getOrCreatePaymentMethod(string $name): void
    {
        $existing = $this->paymentMethodRepository->findOneBy(['name' => $name]);
        if ($existing) {
            return;
        }

        $paymentMethod = (new PaymentMethod())->setName($name);
        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();
    }

    private function getOrCreateVoucherType(string $code, string $name): void
    {
        $existing = $this->voucherTypeRepository->find($code);
        if ($existing) {
            return;
        }

        $voucherType = (new VoucherType())
            ->setCode($code)
            ->setName($name);
        $this->entityManager->persist($voucherType);
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
        if ($existing) {
            return;
        }

        $unit = (new UnitOfMeasure())
            ->setName($name)
            ->setCode($code)
            ->setType($type)
            ->setRatio($ratio)
            ->setReference($reference);

        $this->entityManager->persist($unit);
        $this->entityManager->flush();
    }

    private function getOrCreateCategory(string $name): void
    {
        $existing = $this->categoryRepository->findOneBy(['name' => $name]);
        if ($existing) {
            return;
        }

        $category = (new Category())->setName($name);
        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    private function getOrCreateCustomer(string $ruc, string $businessName): void
    {
        $existing = $this->customerRepository->find($ruc);
        if ($existing) {
            return;
        }

        $customer = (new Customer())
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
            startDate: new \DateTimeImmutable('2020-01-01'),
            endDate: new \DateTimeImmutable('2040-01-01'),
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

            if ($existing) {
                continue;
            }

            $voucherSeries = (new VoucherSeries())
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
